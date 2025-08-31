<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailLog;
use App\Models\Blacklist;
use App\Models\ShortUrl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class ProcessCampaignEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int The ID of the campaign to process.
     */
    protected $campaignId;

    /**
     * Create a new job instance.
     *
     * @param int $campaignId
     * @return void
     */
    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    /**
     * Execute the job.
     * The job prepares data and sends it to a remote API.
     *
     * @return void
     */
    public function handle(): void
    {
        $campaign = Campaign::with(['template', 'smtpServers'])->find($this->campaignId);

        if (!$campaign) {
            Log::error("Campaign job failed: Campaign ID {$this->campaignId} not found.");
            return;
        }

        if ($campaign->status !== 'active') {
            Log::info("Job for campaign ID {$this->campaignId} was stopped because the campaign is no longer active.");
            return;
        }

        try {
            $contactsToSend = Contact::where('campaign_id', $campaign->id)
                ->whereDoesntHave('emailLogs', function ($query) use ($campaign) {
                    $query->where('campaign_id', $campaign->id)
                          ->whereIn('status', ['sent', 'pending']);
                })
                ->whereNotIn('email', Blacklist::pluck('email')->toArray())
                ->get();

            // Get the first active SMTP server associated with the campaign
            $smtpServer = $campaign->smtpServers->first();
            if (!$smtpServer) {
                $campaign->update(['status' => 'failed']);
                Log::error("Campaign ID {$campaign->id} failed: No active SMTP server associated.");
                return;
            }

            $totalContacts = $contactsToSend->count();
            $processedCount = 0;
            $progressUpdateStep = max(1, (int)($totalContacts / 100));

            foreach ($contactsToSend as $contact) {
                $campaign->refresh();
                if ($campaign->status !== 'active') {
                    Log::info("Job for campaign ID {$this->campaignId} was manually paused/stopped.");
                    return;
                }

                $emailLog = null;
                try {
                    // Create a pending entry in the email log
                    $emailLog = EmailLog::create([
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'status' => 'pending',
                        'smtp_server_id' => $smtpServer->id,
                    ]);

                    // Prepare all necessary parameters for the remote API
                    $emailData = $this->prepareEmailDataForApi($campaign, $contact, $emailLog);

                    // Send data to the remote API and handle the response
                    $response = $this->sendToRemoteEmailApi($emailData, $smtpServer->api_endpoint, $smtpServer->api_key);

                    if ($response['success']) {
                        $emailLog->update(['status' => 'sent', 'sent_at' => now()]);
                        Log::info("Email dispatch request successful for {$contact->email}, status set to 'sent'.");
                    } else {
                        $emailLog->update(['status' => 'failed']);
                        Log::error("Email dispatch request failed for {$contact->email}: " . $response['message']);
                    }

                } catch (\Exception $e) {
                    if ($emailLog) {
                        $emailLog->update(['status' => 'failed']);
                    }
                    Log::error("API request failed for {$contact->email}: " . $e->getMessage());
                }

                $processedCount++;
                $this->updateProgress($campaign, $processedCount, $totalContacts, $progressUpdateStep);
            }

            $campaign->update(['status' => 'completed', 'progress' => 100]);
            Log::info("Campaign ID {$campaign->id} completed.");

        } catch (\Exception $e) {
            $campaign->update(['status' => 'failed', 'progress' => 0]);
            Log::error("Fatal error during campaign job ID {$campaign->id}: " . $e->getMessage());
        }
    }

    /**
     * Prepares all email data for sending to the remote API.
     * @return array
     */
    private function prepareEmailDataForApi(Campaign $campaign, Contact $contact, EmailLog $emailLog): array
    {
        $personalizedContent = $this->personalizeContent($campaign->template->html_content, $contact);
        $personalizedContent = $this->addTrackingPixel($personalizedContent, $emailLog);
        $personalizedContent = $this->trackUrls($personalizedContent, $campaign, $emailLog, $contact);
        $personalizedContent = $this->addUnsubscribeLink($personalizedContent, $contact);

        return [
            'to_email' => $contact->email,
            'to_name' => $contact->firstname . ' ' . $contact->name,
            'subject' => $campaign->subject,
            'content' => $personalizedContent,
            'campaign_id' => $campaign->id,
            'email_log_id' => $emailLog->id,
            'from_email' => 'contact@example.com',
            'from_name' => 'My Application',
        ];
    }

    /**
     * Handles HTML content personalization.
     */
    private function personalizeContent(string $content, Contact $contact): string
    {
        return str_replace([
            '{{name}}', '{{firstname}}', '{{email}}', '{{city}}', '{{cp}}',
            '{{department}}', '{{phoneNumber}}', '{{profession}}', '{{habitation}}',
            '{{anciennete}}', '{{statut}}'
        ], [
            $contact->name ?? '', $contact->firstname ?? '', $contact->email ?? '', $contact->city ?? '', $contact->cp ?? '',
            $contact->department ?? '', $contact->phone_number ?? '', $contact->profession ?? '', $contact->habitation ?? '',
            $contact->anciennete ?? '', $contact->statut ?? ''
        ], $content);
    }

    /**
     * Adds the open tracking pixel.
     */
    private function addTrackingPixel(string $content, EmailLog $emailLog): string
    {
        $trackingPixelUrl = url("/track/open/{$emailLog->id}");
        return $content . "<img src=\"{$trackingPixelUrl}\" alt=\"\" width=\"1\" height=\"1\" style=\"display:none;\"/>";
    }

    /**
     * Processes and shortens URLs for click tracking.
     */
    private function trackUrls(string $content, Campaign $campaign, EmailLog $emailLog, Contact $contact): string
    {
        return preg_replace_callback('/<a[^>]*href="([^"]+)"[^>]*>/i', function($matches) use ($campaign, $emailLog, $contact) {
            $originalUrl = $matches[1];
            if (Str::contains($originalUrl, url('/unsubscribe'))) {
                return $matches[0];
            }

            $shortCode = Str::random(8);
            ShortUrl::create([
                'original_url' => $originalUrl,
                'short_code' => $shortCode,
                'campaign_id' => $campaign->id,
                'email_log_id' => $emailLog->id,
                'tracking_data' => [
                    'contact_email' => $contact->email,
                    'contact_id' => $contact->id,
                    'campaign_name' => $campaign->name,
                ],
            ]);

            $trackedUrl = url("/l/{$shortCode}");
            return str_replace($originalUrl, $trackedUrl, $matches[0]);
        }, $content);
    }

    /**
     * Adds the unsubscribe link.
     */
    private function addUnsubscribeLink(string $content, Contact $contact): string
    {
        $unsubscribeUrl = url("/unsubscribe/" . encrypt($contact->email));
        return $content . "<p style='text-align:center; font-size:10px;'><a href=\"{$unsubscribeUrl}\">Se désinscrire</a></p>";
    }

    /**
     * Sends email data to the remote API.
     * Replaces the simulation with a real HTTP call.
     * @param array $emailData
     * @param string $apiUrl The API URL to use.
     * @param string|null $apiKey The API key if needed.
     * @return array
     */
    private function sendToRemoteEmailApi(array $emailData, string $apiUrl, ?string $apiKey = null): array
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($apiKey) {
                $headers['Authorization'] = 'Bearer ' . $apiKey;
            }

            $response = Http::withHeaders($headers)
                            ->post($apiUrl, $emailData);

            return ['success' => $response->successful(), 'message' => $response->body()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Updates the campaign progress.
     */
    private function updateProgress(Campaign $campaign, int $processed, int $total, int $step): void
    {
        if ($total > 0 && ($processed % $step === 0 || $processed === $total)) {
            $progress = min(100, (int)(($processed / $total) * 100));
            $campaign->update(['progress' => $progress]);
        }
    }
}
