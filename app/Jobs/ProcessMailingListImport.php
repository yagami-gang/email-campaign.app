<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MailingList;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMailingListImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mailingListId;
    protected $jsonContent;

    /**
     * Crée une nouvelle instance du job.
     *
     * @param int $mailingListId L'ID de la mailing liste à mettre à jour.
     * @param string $jsonContent Le contenu JSON des contacts.
     * @return void
     */
    public function __construct(int $mailingListId, string $jsonContent)
    {
        $this->mailingListId = $mailingListId;
        $this->jsonContent = $jsonContent;
    }

    /**
     * Exécute le job.
     * Cette méthode contient la logique d'importation des contacts.
     *
     * @return void
     */
    public function handle(): void
    {
        // Récupère la MailingList pour la mettre à jour
        $mailingList = MailingList::find($this->mailingListId);

        // Si la mailing list n'existe pas (supprimée avant le traitement du job?), on log et on arrête.
        if (!$mailingList) {
            Log::error("Job d'importation échoué: MailingList ID {$this->mailingListId} introuvable.");
            return;
        }

        // Met à jour le statut au début du traitement
        $mailingList->update(['status' => 'processing', 'progress' => 0]);

        try {
            DB::beginTransaction();

            $contactsData = json_decode($this->jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Fichier JSON invalide: ' . json_last_error_msg());
            }

            $totalContacts = count($contactsData);
            $processedCount = 0;
            $chunkSize = max(1, (int)($totalContacts / 100)); // Mettre à jour la progression au moins 100 fois, ou par contact si <100

            foreach ($contactsData as $index => $contactData) {
                // Assurez-vous que l'email est toujours présent et valide
                if (empty($contactData['email']) || !filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                    Log::warning('Email invalide ou manquant trouvé lors de l\'import: ' . json_encode($contactData));
                    continue; // Passe au contact suivant
                }

                $contact = Contact::firstOrCreate(
                    ['email' => $contactData['email']],
                    [
                        'name' => $contactData['name'] ?? null,
                        'firstname' => $contactData['firstname'] ?? null,
                        'cp' => $contactData['cp'] ?? null,
                        'department' => $contactData['department'] ?? null,
                        'phone_number' => $contactData['phoneNumber'] ?? null,
                        'city' => $contactData['city'] ?? null,
                        'profession' => $contactData['profession'] ?? null,
                        'habitation' => $contactData['habitation'] ?? null,
                        'anciennete' => $contactData['anciennete'] ?? null,
                        'statut' => $contactData['statut'] ?? null,
                    ]
                );

                // Attacher le contact à la mailing list via la table pivot
                $mailingList->contacts()->attach($contact->id);
                $processedCount++;

                // Met à jour la progression périodiquement
                if ($totalContacts > 0 && ($processedCount % $chunkSize === 0 || $processedCount === $totalContacts)) {
                    $progress = min(100, (int)(($processedCount / $totalContacts) * 100));
                    $mailingList->update(['progress' => $progress]);
                }
            }

            DB::commit();
            // Met à jour le statut et la progression à 100% une fois terminé
            $mailingList->update(['status' => 'completed', 'progress' => 100]);
            Log::info("Mailing liste '{$mailingList->name}' importée avec succès. ID: {$mailingList->id}");

        } catch (\Exception $e) {
            DB::rollBack();
            // En cas d'erreur, met à jour le statut à 'failed'
            $mailingList->update(['status' => 'failed', 'progress' => 0]);
            Log::error("Erreur lors de l'importation de la mailing liste '{$mailingList->name}': " . $e->getMessage());
            // Ici, vous pourriez aussi notifier l'administrateur d'un échec d'import
        }
    }
}
