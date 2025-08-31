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
use Illuminate\Support\Facades\Storage;
use JsonMachine\Items;
use Exception;

class ProcessMailingListImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mailingListId;
    protected $filePath;
    protected $totalContacts;

    /**
     * Crée une nouvelle instance du job.
     *
     * @param int $mailingListId L'ID de la mailing liste.
     * @param string $filePath Le chemin du fichier JSON.
     * @param int $totalContacts Le nombre total de contacts.
     * @return void
     */
    public function __construct(int $mailingListId, string $filePath, int $totalContacts)
    {
        $this->mailingListId = $mailingListId;
        $this->filePath = $filePath;
        $this->totalContacts = $totalContacts;
    }

    /**
     * Exécute le job.
     *
     * @return void
     */
    public function handle(): void
    {
        $mailingList = MailingList::find($this->mailingListId);

        if (!$mailingList) {
            Log::error("Job d'importation échoué: MailingList ID {$this->mailingListId} introuvable.");
            return;
        }

        $mailingList->update(['status' => 'processing', 'progress' => 0]);

        $processedCount = 0;
        $batchSize = 5000; // Taille du lot pour l'insertion en vrac
        $contactsDataBatch = [];
        $progressUpdateStep = max(1, (int)($this->totalContacts / 100)); // Mettre à jour la progression au moins 100 fois

        try {
            $stream = Storage::disk('local')->readStream($this->filePath);
            $contacts = Items::fromStream($stream);

            DB::beginTransaction();

            foreach ($contacts as $index => $contactData) {
                // Validation de base pour éviter d'importer des contacts invalides
                if (empty($contactData['email']) || !filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                    Log::warning('Email invalide ou manquant trouvé lors de l\'import: ' . json_encode($contactData));
                    continue;
                }

                $contactsDataBatch[] = [
                    'email' => $contactData['email'],
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
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Si le lot atteint la taille spécifiée, on insère
                if (count($contactsDataBatch) >= $batchSize) {
                    $this->processBatch($contactsDataBatch, $mailingList);
                    $contactsDataBatch = []; // Réinitialise le tableau pour le prochain lot
                }

                $processedCount++;

                // Met à jour la progression périodiquement
                if ($this->totalContacts > 0 && ($processedCount % $progressUpdateStep === 0 || $processedCount === $this->totalContacts)) {
                    $progress = min(100, (int)(($processedCount / $this->totalContacts) * 100));
                    $mailingList->update(['progress' => $progress]);
                }
            }

            // Traite le dernier lot s'il n'est pas vide
            if (!empty($contactsDataBatch)) {
                $this->processBatch($contactsDataBatch, $mailingList);
            }

            DB::commit();
            $mailingList->update(['status' => 'completed', 'progress' => 100]);
            Log::info("Mailing liste '{$mailingList->name}' importée avec succès. ID: {$mailingList->id}");

            Storage::disk('local')->delete($this->filePath);

        } catch (Exception $e) {
            DB::rollBack();
            $mailingList->update(['status' => 'failed', 'progress' => 0]);
            Log::error("Erreur lors de l'importation de la mailing liste '{$mailingList->name}': " . $e->getMessage());
        }
    }

    /**
     * Traite un lot de contacts en utilisant l'insertion en vrac.
     *
     * @param array $contactsDataBatch Le tableau des contacts à insérer.
     * @param \App\Models\MailingList $mailingList La mailing liste à laquelle attacher les contacts.
     * @return void
     */
    protected function processBatch(array $contactsDataBatch, MailingList $mailingList): void
    {
        // Utilise upsert pour insérer/mettre à jour en vrac
        Contact::upsert(
            $contactsDataBatch,
            ['email'], // Colonnes utilisées pour identifier les doublons
            ['name', 'firstname', 'cp', 'department', 'phone_number', 'city', 'profession', 'habitation', 'anciennete', 'statut'] // Colonnes à mettre à jour
        );

        // Récupère les IDs des contacts insérés ou mis à jour dans ce lot
        $contactEmails = collect($contactsDataBatch)->pluck('email')->unique()->toArray();
        $contactIds = Contact::whereIn('email', $contactEmails)->pluck('id')->toArray();

        // Prépare les données pour la table pivot en vrac
        $pivotData = collect($contactIds)->map(function ($contactId) use ($mailingList) {
            return [
                'contact_id' => $contactId,
                'mailing_list_id' => $mailingList->id,
            ];
        })->toArray();

        // Insère en vrac dans la table pivot
        DB::table('contact_mailing_list')->insertOrIgnore($pivotData);
    }
}
