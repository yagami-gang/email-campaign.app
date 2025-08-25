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
use App\Models\TrackingOpen;
use App\Models\TrackingClick;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Swift_SmtpTransport;
use Swift_Mailer;
use Illuminate\Support\Facades\Log;

class ProcessCampaignEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignId;

    /**
     * Crée une nouvelle instance du job.
     *
     * @param int $campaignId L'ID de la campagne à traiter.
     * @return void
     */
    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    /**
     * Exécute le job.
     * Contient la logique d'envoi d'emails pour la campagne et met à jour la progression.
     *
     * @return void
     */
    public function handle(): void
    {
        // Récupère la Campagne avec ses relations nécessaires
        $campaign = Campaign::with(['template', 'smtpServers'])->find($this->campaignId);

        // Si la campagne n'existe pas ou n'est pas active, on log et on arrête.
        if (!$campaign) {
            Log::error("Job d'envoi de campagne échoué: Campagne ID {$this->campaignId} introuvable.");
            return;
        }
        if ($campaign->status !== 'active') {
            Log::info("Le Job pour la campagne ID {$this->campaignId} a été arrêté car la campagne n'est plus active (statut: {$campaign->status}).");
            return;
        }

        // Met à jour le statut au début du traitement
        $campaign->update(['status' => 'active', 'progress' => 0]);

        try {
            // Récupère tous les contacts (non encore traités pour cette campagne et non blacklistés)
            // Plus tard, cette logique pourra être affinée pour lier les contacts à des mailing lists spécifiques
            $contactsToSend = Contact::whereDoesntHave('emailLogs', function ($query) use ($campaign) {
                $query->where('campaign_id', $campaign->id)
                      ->whereIn('status', ['sent', 'pending']); // Évite de renvoyer si déjà envoyé ou en cours
            })
            ->whereNotIn('email', Blacklist::pluck('email')->toArray()) // Exclut les emails blacklistés
            ->get();

            $smtpServers = $campaign->smtpServers;
            if ($smtpServers->isEmpty()) {
                $campaign->update(['status' => 'failed']);
                Log::error("Campagne ID {$campaign->id} échouée: Aucun serveur SMTP actif n'est associé.");
                return;
            }

            $currentSmtpServerIndex = 0;
            $totalSmtpServers = $smtpServers->count();
            $totalContacts = $contactsToSend->count();
            $processedCount = 0;

            // Met à jour la progression au moins 100 fois, ou par contact si <100
            $chunkSize = max(1, (int)($totalContacts / 100)); 

            foreach ($contactsToSend as $index => $contact) {
                // Re-vérifier le statut de la campagne à chaque itération (si mise en pause manuellement)
                $campaign->refresh(); // Recharge l'état de la campagne depuis la DB
                if ($campaign->status !== 'active') {
                    Log::info("Le Job pour la campagne ID {$this->campaignId} a été mis en pause/arrêté manuellement. Statut: {$campaign->status}");
                    return; // Arrête le job
                }
                
                // Sélectionne le serveur SMTP actuel en mode round-robin
                $smtpServer = $smtpServers[$currentSmtpServerIndex];

                $emailLog = null; // Initialise en dehors du try pour pouvoir y accéder en cas d'erreur
                try {
                    // 1. Log l'email comme "pending"
                    $emailLog = EmailLog::create([
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'status' => 'pending',
                    ]);

                    // 2. Personnalisation du contenu HTML
                    $personalizedContent = $campaign->template->html_content;
                    // Remplace les variables dans le template
                    $personalizedContent = str_replace('{{name}}', $contact->name ?? '', $personalizedContent);
                    $personalizedContent = str_replace('{{firstname}}', $contact->firstname ?? '', $personalizedContent);
                    $personalizedContent = str_replace('{{email}}', $contact->email ?? '', $personalizedContent);
                    $personalizedContent = str_replace('{{city}}', $contact->city ?? '', $personalizedContent);
                    $personalizedContent = str_replace('{{cp}}', $contact->cp ?? '', $personalizedContent);
                    $personalizedContent = str_replace('{{department}}', $contact->department ?? '', $personalizedContent);
                    $personalizedContent = str_replace('{{phoneNumber}}', $contact->phone_number ?? '', $personalizedContent);
                    $personalizedContent = str_replace('{{profession}}', $contact->profession ?? '', $personalizedContent);
                    $personalizedContent = str_replace('{{habitation}}', $contact->habitation ?? '', $personalizedContent);
                    $personalizedContent = str_replace('{{anciennete}}', $contact->anciennete ?? '', $personalizedContent);
                    $personalizedContent = str_replace('{{statut}}', $contact->statut ?? '', $personalizedContent);


                    // 3. Intégration du pixel de suivi d'ouverture
                    // Assurez-vous que APP_URL est configuré dans votre .env
                    $trackingPixelUrl = url("/track/open/{$emailLog->id}");
                    $personalizedContent .= "<img src=\"{$trackingPixelUrl}\" alt=\"\" width=\"1\" height=\"1\" style=\"display:none;\"/>";

                    // 4. Traitement et raccourcissement des URLs pour le suivi des clics
                    $personalizedContent = preg_replace_callback('/<a[^>]*href="([^"]+)"[^>]*>/i', function($matches) use ($campaign, $emailLog, $contact) {
                        $originalUrl = $matches[1];
                        // Vérifie si c'est l'URL de désinscription (à exclure du raccourcissement de suivi de clic)
                        if (Str::contains($originalUrl, url('/unsubscribe'))) {
                            return $matches[0]; // Ne raccourcit pas l'URL de désinscription
                        }
                        
                        // Générer un code court unique
                        $shortCode = Str::random(8); 
                        
                        $shortUrl = ShortUrl::create([
                            'original_url' => $originalUrl,
                            'short_code' => $shortCode,
                            'campaign_id' => $campaign->id,
                            'email_log_id' => $emailLog->id,
                            'tracking_data' => [ // Stocke des données pour analyse future
                                'contact_email' => $contact->email,
                                'contact_id' => $contact->id,
                                'campaign_name' => $campaign->name,
                            ],
                        ]);

                        $trackedUrl = url("/l/{$shortCode}"); // L'URL que le client recevra
                        
                        // Remplace l'URL originale par l'URL raccourcie dans le HTML du lien
                        return str_replace($originalUrl, $trackedUrl, $matches[0]);
                    }, $personalizedContent);

                    // 5. Intégration de l'URL de désinscription
                    // Assurez-vous que APP_URL est configuré dans votre .env
                    $unsubscribeUrl = url("/unsubscribe/" . encrypt($contact->email)); // Crypte l'email pour sécurité
                    $personalizedContent .= "<p style='text-align:center; font-size:10px;'><a href=\"{$unsubscribeUrl}\">Se désinscrire</a></p>";

                    // 6. Configuration du mailer pour utiliser le serveur SMTP sélectionné
                    $transport = new Swift_SmtpTransport(
                        $smtpServer->host,
                        $smtpServer->port,
                        $smtpServer->encryption ?? null
                    );
                    $transport->setUsername($smtpServer->username);
                    $transport->setPassword($smtpServer->password);

                    // Crée un nouveau mailer avec ce transport
                    $customMailer = new Swift_Mailer($transport);
                    Mail::setSwiftMailer($customMailer); // Définit le mailer par défaut pour cette requête

                    // 7. Envoi de l'email
                    Mail::html($personalizedContent, function ($message) use ($campaign, $contact) {
                        $message->to($contact->email, $contact->firstname . ' ' . $contact->name)
                                ->subject($campaign->subject)
                                ->from($campaign->sender_email, $campaign->sender_name);
                    });

                    // 8. Met à jour le log d'email comme "sent"
                    $emailLog->update(['status' => 'sent', 'sent_at' => now()]);
                    Log::info("Email envoyé avec succès à {$contact->email} pour la campagne ID {$campaign->id}.");

                } catch (\Exception $e) {
                    // 9. Met à jour le log d'email comme "failed" en cas d'erreur
                    if ($emailLog) { // S'assurer que le log a été créé avant l'erreur
                        $emailLog->update(['status' => 'failed']);
                    }
                    Log::error("Échec de l'envoi à {$contact->email} pour la campagne ID {$campaign->id}: " . $e->getMessage());
                }

                $processedCount++;

                // Met à jour la progression périodiquement
                if ($totalContacts > 0 && ($processedCount % $chunkSize === 0 || $processedCount === $totalContacts)) {
                    $progress = min(100, (int)(($processedCount / $totalContacts) * 100));
                    $campaign->update(['progress' => $progress]);
                }

                // Passe au serveur SMTP suivant pour le prochain email (round-robin)
                $currentSmtpServerIndex = ($currentSmtpServerIndex + 1) % $totalSmtpServers;

                // Optionnel: Mettre en pause entre les envois pour respecter la fréquence de campagne
                if ($campaign->send_frequency_minutes > 0) {
                    sleep($campaign->send_frequency_minutes * 60); // Attend X minutes
                }
            }

            // Met à jour le statut et la progression à 100% une fois terminé
            $campaign->update(['status' => 'completed', 'progress' => 100]);
            Log::info("Campagne ID {$campaign->id} terminée.");

        } catch (\Exception $e) {
            // En cas d'erreur fatale dans le Job (hors envoi d'un email spécifique)
            $campaign->update(['status' => 'failed', 'progress' => 0]);
            Log::error("Erreur fatale lors du Job de campagne ID {$campaign->id}: " . $e->getMessage());
        }
    }
}
