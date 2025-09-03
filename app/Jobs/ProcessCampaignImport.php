<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonMachine\Items;
use Throwable; // Utilisez Throwable pour attraper les Exceptions et les Erreurs

class ProcessCampaignImport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Empêche l'exécution de plusieurs jobs d'import pour la même campagne en même temps.
     * Le verrou est libéré à la fin du job ou après 1 heure d'échec.
     */
    public function uniqueId(): int
    {
        return $this->campaignId;
    }

    public int $tries = 3; // Tente le job 3 fois en cas d'échec
    public int $timeout = 3600; // Timeout d'une heure pour les très gros fichiers

    /**
     * Mappage des clés du fichier JSON vers les colonnes de la base de données.
     * Centralise la logique de transformation des données.
     *
     * @const array<string, string>
     */
    private const COLUMN_MAP = [
        'email'        => 'email',
        'name'         => 'name',
        'firstname'    => 'firstname',
        'cp'           => 'cp',
        'department'   => 'department',
        'phoneNumber'  => 'phone_number',
        'city'         => 'city',
        'profession'   => 'profession',
        'habitation'   => 'habitation',
        'anciennete'   => 'anciennete',
        'statut'       => 'statut',
    ];

    /**
     * Crée une nouvelle instance du job.
     *
     * @param int $campaignId L'ID de la campagne.
     * @param array $filePaths Le tableau des chemins de fichiers JSON à importer.
     * @param string $tableName Le nom de la table de contacts dynamique.
     */
    public function __construct(
        protected int $campaignId,
        protected array $filePaths,
        protected string $tableName
    ) {}

    /**
     * Exécute le job d'importation.
     *
     * @return void
     */
    public function handle(): void
    {
        $campaign = Campaign::find($this->campaignId);
        if (!$campaign) {
            Log::critical("Importation annulée : Campagne ID {$this->campaignId} introuvable.");
            return;
        }

        $campaign->update(['status' => 'processing', 'progress' => 0]);
        Log::info("Début de l'importation pour la campagne #{$campaign->id} '{$campaign->name}'.");

        $totalContactsProcessed = 0;
        $totalContactsSkipped = 0;
        $batchSize = 2000; 
        $contactDataBatch = [];

        try {
            // Estimer le nombre total de contacts pour la barre de progression
            $estimatedTotalContacts = $this->estimateTotalContacts();

            foreach ($this->filePaths as $filePath) {
                if (!Storage::disk('local')->exists($filePath)) {
                    Log::warning("Fichier introuvable pour la campagne #{$campaign->id}, il est ignoré : {$filePath}");
                    continue;
                }

                Log::info("Traitement du fichier '{$filePath}' pour la campagne #{$campaign->id}.");

                $stream = Storage::disk('local')->readStream($filePath);
                $contacts = Items::fromStream($stream);

                foreach ($contacts as $contactObject) {
                    $contactData = (array) $contactObject;

                    if (empty($contactData['email']) || !filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                        $totalContactsSkipped++;
                        continue;
                    }

                    $dataToInsert = [];
                    foreach (self::COLUMN_MAP as $jsonKey => $dbColumn) {
                        if (isset($contactData[$jsonKey])) {
                            $dataToInsert[$dbColumn] = ($dbColumn === 'email')
                                ? strtolower(trim($contactData[$jsonKey]))
                                : $contactData[$jsonKey];
                        }
                    }

                    // N'ajoutez que s'il y a un email valide
                    if (!empty($dataToInsert['email'])) {
                        $contactDataBatch[] = $dataToInsert + ['imported_at' => now()];
                        $totalContactsProcessed++;
                    } else {
                         $totalContactsSkipped++;
                    }

                    // Vider le lot dans la base de données
                    if (count($contactDataBatch) >= $batchSize) {
                        $this->processBatch($contactDataBatch);
                        $contactDataBatch = [];

                        // Mettre à jour la progression
                        if ($estimatedTotalContacts > 0) {
                            $progress = (int)(($totalContactsProcessed / $estimatedTotalContacts) * 100);
                            $campaign->update(['progress' => min(99, $progress)]); // Ne pas mettre 100% avant la fin
                        }
                    }
                    unset($contactObject); // Aide le garbage collector
                }
            }

            // Traiter le dernier lot restant
            if (!empty($contactDataBatch)) {
                $this->processBatch($contactDataBatch);
            }

            // Mise à jour finale après succès
            $finalCount = DB::table($this->tableName)->count();
            $campaign->update([
                'status' => 'scheduled', // Statut final prêt pour l'envoi
                'progress' => 100,
                'nbre_contacts' => $finalCount
            ]);

            Log::info("Importation terminée pour la campagne #{$campaign->id}. Contacts importés/mis à jour: {$finalCount}. Contacts ignorés (email invalide): {$totalContactsSkipped}.");

        } catch (Throwable $e) {
            // En cas d'erreur grave, on appelle la méthode failed()
            $this->fail($e);
        }
    }

    /**
     * Gère l'échec du job.
     * Cette méthode est appelée par Laravel lorsque toutes les tentatives ont échoué.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        $campaign = Campaign::find($this->campaignId);
        if ($campaign) {
            $campaign->update(['status' => 'failed']);
            Log::error("ÉCHEC de l'importation pour la campagne #{$campaign->id} '{$campaign->name}'. Erreur: " . $exception->getMessage(), [
                'trace' => $exception->getTraceAsString()
            ]);
        }
    }

    /**
     * Insère ou met à jour un lot de contacts dans la table de la campagne.
     *
     * @param array $contactDataBatch
     * @return void
     */
    protected function processBatch(array $contactDataBatch): void
    {
        // Les colonnes à mettre à jour si l'email existe déjà
        $updateColumns = collect(self::COLUMN_MAP)->values()->forget('email')->toArray();
        $updateColumns[] = 'imported_at';

        DB::table($this->tableName)->upsert(
            $contactDataBatch,
            ['email'],      // Colonne unique pour la correspondance
            $updateColumns  // Champs à mettre à jour en cas de doublon
        );
    }

    /**
     * Estime le nombre total de contacts en lisant rapidement les fichiers.
     *
     * @return int
     */
    private function estimateTotalContacts(): int
    {
        $total = 0;
        foreach ($this->filePaths as $filePath) {
            try {
                if (Storage::disk('local')->exists($filePath)) {
                    $stream = Storage::disk('local')->readStream($filePath);
                    $items = Items::fromStream($stream);
                    // iterator_count est efficace en mémoire pour compter les éléments d'un générateur
                    $total += iterator_count($items);
                }
            } catch (Exception $e) {
                Log::warning("Impossible de pré-compter les contacts pour le fichier {$filePath}: " . $e->getMessage());
                continue;
            }
        }
        return $total;
    }
}
