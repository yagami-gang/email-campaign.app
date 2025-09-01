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

    /**
     * L'ID de la campagne en cours de traitement.
     */
    protected int $campaignId;

    /**
     * Le chemin du fichier JSON contenant les contacts.
     */
    protected string $filePath;

    /**
     * Le nombre total de contacts attendus dans le fichier.
     */
    protected int $totalContacts;

    /**
     * Le nom de la table de contacts à remplir.
     */
    protected string $tableName;

    /**
     * Crée une nouvelle instance du job.
     *
     * @param int $campaignId L'ID de la campagne.
     * @param string $filePath Le chemin du fichier JSON.
     * @param int $totalContacts Le nombre total de contacts.
     * @param string $tableName Le nom de la table de contacts.
     * @return void
     */
    public function __construct(int $campaignId, string $filePath, int $totalContacts, string $tableName)
    {
        $this->campaignId = $campaignId;
        $this->filePath = $filePath;
        $this->totalContacts = $totalContacts;
        $this->tableName = $tableName;
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
            Log::error("Importation échouée: Campagne ID {$this->campaignId} introuvable.");
            return;
        }

        $campaign->update(['status' => 'processing', 'progress' => 0]);

        $processedCount = 0;
        $skippedCount = 0;
        $batchSize = 5000;
        $contactDataBatch = [];
        $progressUpdateStep = max(1, (int)($this->totalContacts / 100));

        try {
            $stream = Storage::disk('local')->readStream($this->filePath);
            $contacts = Items::fromStream($stream);

            foreach ($contacts as $index => $contactData) {
                if (empty($contactData['email']) || !filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                    Log::warning("Contact invalide ou manquant ignoré: " . json_encode($contactData));
                    $skippedCount++;
                    continue;
                }

                $contactDataBatch[] = [
                    'email' => strtolower($contactData['email']),
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

                $processedCount++;

                if (count($contactDataBatch) >= $batchSize) {
                    $this->processBatch($contactDataBatch);
                    $contactDataBatch = [];
                }

                if ($this->totalContacts > 0 && ($processedCount % $progressUpdateStep === 0 || $processedCount === $this->totalContacts)) {
                    $progress = min(100, (int)(($processedCount / $this->totalContacts) * 100));
                    $campaign->update(['progress' => $progress]);
                }
            }

            if (!empty($contactDataBatch)) {
                $this->processBatch($contactDataBatch);
            }

            $successCount = $processedCount - $skippedCount;
            $campaign->update([
                'status' => 'completed',
                'progress' => 100,
                'nbre_contacts' => DB::table($this->tableName)->count()
            ]);
            Log::info("Importation de la campagne '{$campaign->name}' terminée. ID: {$campaign->id}. Statistiques: {$successCount} contacts importés, {$skippedCount} contacts ignorés.");

        } catch (Exception $e) {
            $campaign->update(['status' => 'failed', 'progress' => 0]);
            Log::error("Erreur lors de l'importation de la campagne '{$campaign->name}': " . $e->getMessage());
        }
    }

    /**
     * Traite un lot de contacts en les insérant/mettant à jour dans la table de contacts.
     *
     * @param array $contactDataBatch Le tableau des données de contacts.
     * @return void
     */
    protected function processBatch(array $contactDataBatch): void
    {
        DB::table($this->tableName)->upsert(
            $contactDataBatch,
            ['email'],
            ['name', 'firstname', 'cp', 'department', 'phone_number', 'city', 'profession', 'habitation', 'anciennete', 'statut', 'updated_at']
        );
    }
}
