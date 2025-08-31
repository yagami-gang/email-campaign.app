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
            Log::error("Importation échouée: Campagne ID {$this->campaignId} introuvable.");
            return;
        }

        $campaign->update(['status' => 'processing', 'progress' => 0]);

        $processedCount = 0;
        $skippedCount = 0;
        $batchSize = 5000;
        $contactEmailsBatch = [];
        $contactDataLookup = [];
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

                $email = strtolower($contactData['email']);
                $contactEmailsBatch[] = $email;
                $contactDataLookup[$email] = [
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

                if (count($contactEmailsBatch) >= $batchSize) {
                    $this->processBatchAndAttach($campaign, $contactEmailsBatch, $contactDataLookup);
                    $contactEmailsBatch = [];
                    $contactDataLookup = [];
                }

                if ($this->totalContacts > 0 && ($processedCount % $progressUpdateStep === 0 || $processedCount === $this->totalContacts)) {
                    $progress = min(100, (int)(($processedCount / $this->totalContacts) * 100));
                    $campaign->update(['progress' => $progress]);
                }
            }

            if (!empty($contactEmailsBatch)) {
                $this->processBatchAndAttach($campaign, $contactEmailsBatch, $contactDataLookup);
            }

            $successCount = $processedCount - $skippedCount;
            $campaign->update(['status' => 'completed', 'progress' => 100]);
            Log::info("Importation de la campagne '{$campaign->name}' terminée. ID: {$campaign->id}. Statistiques: {$successCount} contacts importés, {$skippedCount} contacts ignorés.");

            Storage::disk('local')->delete($this->filePath);

        } catch (Exception $e) {
            $campaign->update(['status' => 'failed', 'progress' => 0]);
            Log::error("Erreur lors de l'importation de la campagne '{$campaign->name}': " . $e->getMessage());
        }
    }

    /**
     * Traite un lot de contacts en les insérant/mettant à jour, puis en les attachant à la campagne.
     *
     * @param Campaign $campaign
     * @param array $contactEmailsBatch Le tableau des emails à traiter.
     * @param array $contactDataLookup Le tableau des données pour chaque email.
     * @return void
     */
    protected function processBatchAndAttach(Campaign $campaign, array $contactEmailsBatch, array $contactDataLookup): void
    {
        // Étape 1: Insérer ou mettre à jour les contacts dans la table 'contacts'
        $contactDataForUpsert = array_values($contactDataLookup);
        Contact::upsert(
            $contactDataForUpsert,
            ['email'], // La clé unique est uniquement 'email'
            ['name', 'firstname', 'cp', 'department', 'phone_number', 'city', 'profession', 'habitation', 'anciennete', 'statut', 'updated_at']
        );

        // Étape 2: Récupérer les IDs des contacts insérés ou mis à jour
        $contactIdsToAttach = Contact::whereIn('email', $contactEmailsBatch)->pluck('id');

        // Étape 3: Lier les contacts à la campagne via la table de pivot
        $campaign->contacts()->syncWithoutDetaching($contactIdsToAttach);
    }
}
