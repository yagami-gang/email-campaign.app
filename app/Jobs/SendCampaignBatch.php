<?php

namespace App\Jobs;

use Throwable;
use Carbon\Carbon;
use App\Models\Campaign;
use App\Models\EmailLog;
use App\Models\ShortUrl;
use App\Models\SmtpServer;
use Illuminate\Support\Str;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SendCampaignBatch implements ShouldQueue, ShouldBeUnique
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Le nombre de fois où le job peut être tenté.
     * @var int
     */
    public int $tries = 5;

    /**
     * Le nombre de secondes pendant lesquelles le job peut s'exécuter avant d'expirer.
     * @var int
     */
    public int $timeout = 7200; // 1 heure

    /**
     * Indique si le job doit être marqué comme échoué en cas de timeout.
     * @var bool
     */
    public bool $failOnTimeout = true;

    /**
     * Crée une instance du job.
     *
     * @param int $campaignId L'ID de la campagne parente.
     * @param int $smtpServerId L'ID du serveur SMTP que ce job doit utiliser.
     */
    public function __construct(
        protected int $campaignId,
        protected int $smtpServerId
    ) {}

    /**
     * Calcule un identifiant unique pour le job.
     * Empêche que le même job (pour la même campagne et le même serveur) soit en file d'attente plusieurs fois.
     */
    public function uniqueId(): string
    {
        return $this->campaignId . '-' . $this->smtpServerId;
    }

    /**
     * Exécute le job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle(): void
    {
        // Si le lot (batch) parent a été annulé, on arrête immédiatement.
        if ($this->batch()?->cancelled()) {
            return;
        }

        $campaign = Campaign::find($this->campaignId);
        $smtpServer = SmtpServer::find($this->smtpServerId);

        if (!$campaign || !$smtpServer) {
            Log::error("Job travailleur annulé : Campagne #{$this->campaignId} ou Serveur SMTP #{$this->smtpServerId} introuvable.");
            return;
        }

        // Récupérer les données de la table pivot pour ce serveur et cette campagne
        $pivotData = $campaign->smtpServers()->where('smtp_server_id', $smtpServer->id)->first()->pivot;

        Log::info("Job travailleur démarré pour Campagne #{$campaign->id} avec Serveur #{$smtpServer->id} ('{$smtpServer->name}').");

       // --- GESTION DE LA DATE DE DÉPART (scheduled_at) ---
       if ($pivotData->scheduled_at) {
        $scheduledAt = Carbon::parse($pivotData->scheduled_at);
        if ($scheduledAt->isFuture()) {
            Log::info("Job pour Campagne #{$campaign->id}/Serveur #{$smtpServer->id} est planifié pour {$scheduledAt->toDateTimeString()}. On le remet en file d'attente.");
            $this->release($scheduledAt->diffInSeconds(now()) + 5); // +5s de marge
            return;
        }
    }

    Log::info("Job travailleur démarré pour Campagne #{$campaign->id} avec Serveur #{$smtpServer->id}.");

        // Initialisation des variables pour la limitation de débit
        $frequencySeconds = $pivotData->send_frequency_minutes > 0 ? $pivotData->send_frequency_minutes * 60 : 0;
        $maxDailySends = $pivotData->max_daily_sends;
        $dailySentCacheKey = "campaign:{$campaign->id}:smtp:{$smtpServer->id}:date:" . now()->format('Y-m-d');

        // Requête performante pour trouver les contacts à traiter
        $contactsQuery = DB::table($campaign->nom_table_contact . ' as c')
            ->select('c.*')
            ->leftJoin('email_logs as el', function ($join) use ($campaign) {
                $join->on('c.email', '=', 'el.contact_email')->where('el.campaign_id', $campaign->id);
            })
            ->leftJoin('blacklists as b', 'c.email', '=', 'b.email')
            ->whereNull('el.id')->whereNull('b.id');

        foreach ($contactsQuery->cursor() as $contact) {
            if ($this->isStopped($campaign->id)) {
                Log::warning("Le job travailleur pour la campagne #{$campaign->id} a été stoppé.");
                return;
            }

            // --- GESTION DU QUOTA JOURNALIER (max_daily_sends) ---
            if ($maxDailySends > 0) {
                $dailySentCount = Cache::get($dailySentCacheKey, 0);
                if ($dailySentCount >= $maxDailySends) {
                    Log::info("Quota journalier de {$maxDailySends} atteint pour Campagne #{$campaign->id}/Serveur #{$smtpServer->id}. Arrêt du job pour aujourd'hui.");
                    return; // Le job s'arrête, il sera relancé demain par le scheduler
                }
            }

            // Traitement du contact
            $sendSuccess = $this->processContact($campaign, $smtpServer, $pivotData, $contact);

            if ($sendSuccess) {
                if ($maxDailySends > 0) {
                    Cache::increment($dailySentCacheKey);
                    Cache::expire($dailySentCacheKey, now()->addHours(25));
                }

                // --- GESTION DE LA FRÉQUENCE (send_frequency_minutes) ---
                if ($frequencySeconds > 0) {
                    sleep($frequencySeconds);
                }
            }
        }

        Log::info("Job travailleur terminé pour Campagne #{$campaign->id} avec Serveur #{$smtpServer->id}.");
    }

     /**
     * Traite un contact et retourne un booléen indiquant le succès.
     * @return bool
     */
    private function processContact(Campaign $campaign, SmtpServer $smtpServer, object $pivotData, object $contact): bool
    {
        $log = null;
        try {
            DB::transaction(function () use ($campaign, $smtpServer, $pivotData, $contact, &$log) {
                $log = EmailLog::create([
                    'campaign_id'    => $campaign->id, 'contact_email'  => $contact->email,
                    'smtp_server_id' => $smtpServer->id, 'status'         => 'pending',
                ]);
                $emailData = $this->prepareEmailData($campaign, $pivotData, $contact, $log);
                $response = $this->sendToRemoteEmailApi($emailData, $smtpServer->url, $smtpServer->api_key ?? null);
                if ($response['success']) {
                    $log->update(['status' => 'sent', 'sent_at' => now()]);
                } else {
                    throw new \Exception("Échec de l'envoi API : " . $response['message']);
                }
            }, 1);

            $this->updateProgress($campaign);
            return true;
        } catch (Throwable $e) {
            if ($log) {
                $log->update(['status' => 'failed', 'error_message' => substr($e->getMessage(), 0, 255)]);
            }
            return false;
        }
    }

    /**
     * Prépare toutes les données de l'email pour l'envoi à l'API distante.
     *
     * @return array
     */
    private function prepareEmailData(Campaign $campaign, object $pivotData, object $contact, EmailLog $emailLog): array
    {
        $personalizedContent = $this->personalizeContent($campaign->template->html_content, $contact);
        $personalizedContent = $this->addTrackingPixel($personalizedContent, $emailLog);
        $personalizedContent = $this->trackUrls($personalizedContent, $campaign, $emailLog, $contact);
        $personalizedContent = $this->addUnsubscribeLink($personalizedContent, $contact);

        return [
            'to_email' => $contact->email,
            'to_name' => ($contact->firstname ?? '') . ' ' . ($contact->name ?? ''),
            'subject' => $campaign->subject,
            'content' => $personalizedContent,
            'from_email' => $pivotData->sender_email,
            'from_name' => $pivotData->sender_name,
            'campaign_id' => $campaign->id,
            'email_log_id' => $emailLog->id, // Pour le suivi des retours API
        ];
    }

    /**
     * Gère la personnalisation du contenu HTML.
     */
    private function personalizeContent(string $content, object $contact): string
    {
        // Utilise un tableau de mapping pour plus de clarté
        $placeholders = [
            '{{name}}' => $contact->name ?? '', '{{firstname}}' => $contact->firstname ?? '',
            '{{email}}' => $contact->email ?? '', '{{city}}' => $contact->city ?? '',
            '{{cp}}' => $contact->cp ?? '', '{{department}}' => $contact->department ?? '',
            '{{phoneNumber}}' => $contact->phone_number ?? '', '{{profession}}' => $contact->profession ?? '',
            '{{habitation}}' => $contact->habitation ?? '', '{{anciennete}}' => $contact->anciennete ?? '',
            '{{statut}}' => $contact->statut ?? ''
        ];
        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Ajoute le pixel de suivi d'ouverture.
     */
    private function addTrackingPixel(string $content, EmailLog $emailLog): string
    {
        $trackingPixelUrl = route('tracking.open', $emailLog->id);
        return $content . "<img src=\"{$trackingPixelUrl}\" alt=\"\" width=\"1\" height=\"1\" style=\"display:none;\"/>";
    }

    /**
     * Traite et raccourcit les URLs pour le suivi des clics.
     */
    private function trackUrls(string $content, Campaign $campaign, EmailLog $emailLog, object $contact): string
    {
        return preg_replace_callback('/<a[^>]*href="([^"]+)"[^>]*>/i', function($matches) use ($campaign, $emailLog, $contact) {
            $originalUrl = $matches[1];
            // Ne pas tracker les liens de désinscription ou les ancres
            if (Str::contains($originalUrl, ['unsubscribe', '#'])) {
                return $matches[0];
            }

            $shortCode = Str::random(8);
            ShortUrl::create([
                'original_url' => $originalUrl,
                'short_code' => $shortCode,
                'campaign_id' => $campaign->id,
                'email_log_id' => $emailLog->id,
            ]);

            $trackedUrl = route('tracking.click', $shortCode);
            return str_replace($originalUrl, $trackedUrl, $matches[0]);
        }, $content);
    }

    /**
     * Ajoute le lien de désinscription.
     */
    private function addUnsubscribeLink(string $content, object $contact): string
    {
        $unsubscribeUrl = route('unsubscribe', ['email' => encrypt($contact->email)]);
        return $content . "<p style='text-align:center; font-size:10px;'><a href=\"{$unsubscribeUrl}\">Se désinscrire</a></p>";
    }

    /**
     * Envoie les données de l'email à l'API distante.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    private function sendToRemoteEmailApi(array $emailData, string $apiUrl, ?string $apiKey): array
    {
        try {
            $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
            if ($apiKey) {
                $headers['Authorization'] = 'Bearer ' . $apiKey;
            }

            $response = Http::withHeaders($headers)->timeout(30)->post($apiUrl, $emailData);

            return ['success' => $response->successful(), 'message' => $response->body()];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Met à jour la progression de la campagne de manière atomique et thread-safe.
     */
    private function updateProgress(Campaign $campaign): void
    {
        // Incrémente le compteur atomiquement pour éviter les race conditions
        $newSentCount = DB::table('campaigns')->where('id', $campaign->id)->increment('sent_count');

        if ($campaign->nbre_contacts > 0) {
            $progress = (int)(($newSentCount / $campaign->nbre_contacts) * 100);
            // On met à jour la progression sans recharger le modèle entier
            DB::table('campaigns')->where('id', $campaign->id)->update(['progress' => min(99, $progress)]);
            if ($newSentCount >= $campaign->nbre_contacts) {
                DB::table('campaigns')->where('id', $campaign->id)->update(['status' => 'completed', 'progress' => 100]);
            }
        }
    }

    /**
     * Vérifie si le job doit s'arrêter prématurément.
     */
    private function isStopped(int $campaignId): bool
    {
        if ($this->batch()?->cancelled()) {
            return true;
        }
        // Interroge directement la BDD pour avoir l'état le plus frais possible.
        return DB::table('campaigns')->where('id', $campaignId)->value('status') !== 'running';
    }
}
