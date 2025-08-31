<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonMachine\Items;
use Exception;

class ProcessCampaignImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignId;
    protected $filePath;
    protected $totalContacts;

    /**
     * Crée une nouvelle instance du job.
     *
     * @param int $campaignId L'ID de la campagne.
     * @param string $filePath Le chemin du fichier JSON.
     * @param int $totalContacts Le nombre total de contacts.
     * @return void
     */
    public function __construct(int $campaignId, string $filePath, int $totalContacts)
    {
        $this->campaignId = $campaignId;
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
        $campaign = Campaign::find($this->campaignId);

        if (!$campaign) {
            Log::error("Job d'importation échoué: Campagne ID {$this->campaignId} introuvable.");
            return;
        }

        // Mettre à jour le statut et la progression initiale
        $campaign->update(['status' => 'processing', 'progress' => 0]);

        $processedCount = 0;
        $batchSize = 5000;
        $contactsDataBatch = [];
        $progressUpdateStep = max(1, (int)($this->totalContacts / 100));

        try {
            $stream = Storage::disk('local')->readStream($this->filePath);
            $contacts = Items::fromStream($stream);

            foreach ($contacts as $index => $contactData) {
                // Validation de base pour éviter d'importer des contacts invalides
                if (empty($contactData['email']) || !filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                    Log::warning('Email invalide ou manquant lors de l\'import: ' . json_encode($contactData));
                    continue;
                }

                $contactsDataBatch[] = [
                    'campaign_id' => $this->campaignId,
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
                    $this->processBatch($contactsDataBatch);
                    $contactsDataBatch = [];
                }

                $processedCount++;

                // Met à jour la progression périodiquement
                if ($this->totalContacts > 0 && ($processedCount % $progressUpdateStep === 0 || $processedCount === $this->totalContacts)) {
                    $progress = min(100, (int)(($processedCount / $this->totalContacts) * 100));
                    $campaign->update(['progress' => $progress]);
                }
            }

            // Traite le dernier lot s'il n'est pas vide
            if (!empty($contactsDataBatch)) {
                $this->processBatch($contactsDataBatch);
            }

            $campaign->update(['status' => 'completed', 'progress' => 100]);
            Log::info("Campagne '{$campaign->name}' importée avec succès. ID: {$campaign->id}");

            // Nettoyage du fichier
            Storage::disk('local')->delete($this->filePath);

        } catch (Exception $e) {
            $campaign->update(['status' => 'failed', 'progress' => 0]);
            Log::error("Erreur lors de l'importation de la campagne '{$campaign->name}': " . $e->getMessage());
        }
    }

    /**
     * Traite un lot de contacts en utilisant l'insertion ou la mise à jour en vrac.
     *
     * @param array $contactsDataBatch Le tableau des contacts à insérer.
     * @return void
     */
    protected function processBatch(array $contactsDataBatch): void
    {
        // Utilise upsert pour insérer/mettre à jour en vrac
        Contact::upsert(
            $contactsDataBatch,
            ['email', 'campaign_id'], // Clés uniques pour les doublons
            ['name', 'firstname', 'cp', 'department', 'phone_number', 'city', 'profession', 'habitation', 'anciennete', 'statut'] // Colonnes à mettre à jour
        );
    }
}
