<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonMachine\Items;

use App\Models\Campaign;
use App\Models\Json_file; // adapte si ton modèle a un autre nom/namespace

Route::get('/api/cron/import-json-files', function (Request $request) {

    // (Optionnel) mini-protection par token:
    // if ($request->header('X-CRON-TOKEN') !== env('CRON_TOKEN')) abort(403);

    @set_time_limit(0);
    ini_set('memory_limit', '1024M');

    // === Mapping JSON -> colonnes DB (repris du job) ===
    $COLUMN_MAP = [
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

    // Quota & batch fixes (pas de .env)
    $LINES_PER_RUN = 2000;  // <= lignes traitées / fichier / passage
    $BATCH_SIZE    = 50;    // <= upsert par paquets de 50

    // Colonnes à mettre à jour (tout sauf email)
    $updateColumns = array_values(array_filter(array_values($COLUMN_MAP), fn($c) => $c !== 'email'));
    $updateColumns[] = 'imported_at';

    // Ouvre un stream depuis chemin relatif Storage::local ou absolu
    $openStream = function (string $filePath) {
        $disk = Storage::disk('local');
        $root = str_replace('\\', '/', $disk->path(''));         // .../storage/app/
        $given = str_replace('\\', '/', $filePath);

        if (str_starts_with($given, $root)) {
            $relative = ltrim(substr($given, strlen($root)), '/'); // ex: private/xxx.json
            if (!$disk->exists($relative)) return [null, "introuvable: {$relative}"];
            $stream = $disk->readStream($relative);
            if ($stream === false) return [null, "readStream ko: {$relative}"];
            return [$stream, null];
        }
        // Absolu hors storage/app
        $stream = @fopen($given, 'r');
        if ($stream === false) return [null, "fopen ko: {$given}"];
        return [$stream, null];
    };

    // Upsert batch
    $processBatch = function (string $tableName, array &$batch) use ($updateColumns): int {
        if (empty($batch)) return 0;

        // 1) Dédupe le batch par email (dernière occurrence garde la main)
        $byEmail = [];
        foreach ($batch as $row) {
            if (!isset($row['email'])) continue;
            $byEmail[$row['email']] = $row;
        }
        $uniqueRows = array_values($byEmail);
        $emails     = array_keys($byEmail);
        if (empty($emails)) { $batch = []; return 0; }

        // 2) Emails déjà présents en base
        $existing = DB::table($tableName)->whereIn('email', $emails)->pluck('email')->all();
        $existingSet = array_flip($existing);

        // 3) Compter les **nouveaux** (inserts)
        $insertCount = 0;
        foreach ($emails as $e) {
            if (!isset($existingSet[$e])) $insertCount++;
        }

        // 4) Upsert
        DB::table($tableName)->upsert($uniqueRows, ['email'], $updateColumns);

        // 5) Reset du batch et retour du nb d'inserts
        $batch = [];
        return $insertCount;
    };


    // Fichiers encore en cours d'import
    $jsonFiles = Json_file::where('status', 'importation_en_cours')
        ->orderBy('id')
        ->get();

    $summary = [
        'checked' => $jsonFiles->count(),
        'processed_files' => [],
    ];

    foreach ($jsonFiles as $jf) {
        $filePath = $jf->file_path;
        $offset   = (int) $jf->nbre_lignes_traitees; // lignes déjà traitées (offset)
        $quota    = $LINES_PER_RUN;

        $campaign = Campaign::find($jf->campaign_id);
        if (!$campaign) {
            Log::warning("Campagne #{$jf->campaign_id} introuvable (json_files #{$jf->id}).");
            // on marque terminé pour ne pas boucler dessus
            $jf->update(['status' => 'importation_terminee']);
            $summary['processed_files'][] = [
                'json_file_id' => $jf->id,
                'file' => $filePath,
                'status' => 'skipped_campaign_missing',
            ];
            continue;
        }

        if (empty($campaign->nom_table_contact)) {
            Log::error("nom_table_contact manquant pour campagne #{$campaign->id}.");
            $summary['processed_files'][] = [
                'json_file_id' => $jf->id,
                'file' => $filePath,
                'status' => 'skipped_missing_table',
            ];
            continue;
        }
        $tableName = $campaign->nom_table_contact;

        // Marque la campagne comme en import
        $campaign->update(['status' => 'importing'] + ($campaign->progress >= 0 ? [] : ['progress' => 0]));

        [$stream, $err] = $openStream($filePath);
        if ($err) {
            Log::warning("Ouverture échouée ({$filePath}): {$err}");
            $summary['processed_files'][] = [
                'json_file_id' => $jf->id,
                'file' => $filePath,
                'status' => 'open_failed',
                'error' => $err,
            ];
            continue;
        }

        $processedThisRun = 0; // lignes lues cette passe (qu'elles soient importées ou ignorées)
        $importedThisRun  = 0; // lignes effectivement upsertées
        $skippedInvalid   = 0; // emails invalides
        $batch = [];
        $endedByQuota = false;

        try {
            Log::info("Import partiel: fichier='{$filePath}', campagne=#{$campaign->id}, table={$tableName}, offset={$offset}, quota={$quota}");

            $items = Items::fromStream($stream);

            $index = 0; // index global dans le fichier
            foreach ($items as $contactObject) {
                // --- Skip des lignes déjà traitées ---
                if ($index < $offset) { $index++; continue; }

                // --- Arrêt si on a atteint le quota de cette passe ---
                if ($processedThisRun >= $quota) { $endedByQuota = true; break; }

                $index++;
                $processedThisRun++;

                $contactData = (array) $contactObject;

                // Email requis + valide
                $email = $contactData['email'] ?? null;
                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skippedInvalid++;
                    continue;
                }

                // Mapping (reprend ta logique sans isset)
                $row = [];
                foreach ($COLUMN_MAP as $jsonKey => $dbColumn) {
                    $row[$dbColumn] = ($dbColumn === 'email')
                        ? strtolower(trim(($contactData[$jsonKey] ?? null)))
                        : ($contactData[$jsonKey] ?? null);
                }

                if (!empty($row['email'])) {
                    $row['imported_at'] = now();
                    $batch[] = $row;
                } else {
                    $skippedInvalid++;
                }

                // Flush par lots de 50
                if (count($batch) >= $BATCH_SIZE) {
                    $inserted = $processBatch($tableName, $batch);
                    $importedThisRun += $inserted;
                }

                unset($contactObject);
            }

            // Flush final
            $inserted = $processBatch($tableName, $batch);
            $importedThisRun += $inserted;

            // Mise à jour des compteurs du fichier
            $jf->nbre_lignes_traitees  = $offset + $processedThisRun;
            $jf->nbre_lignes_importees = $jf->nbre_lignes_importees + $importedThisRun;

            // Si on n'a PAS atteint le quota, c'est qu'on est allé au bout du fichier
            if (!$endedByQuota) {
                $jf->status = 'importation_terminee';
            }
            $jf->save();

            // MAJ campagne (compte total en base)
            $finalCount = DB::table($tableName)->count();
            $campaign->update(['nbre_contacts' => $finalCount]);

            // Si plus aucun fichier en cours pour cette campagne -> imported + progress=100
            $remaining = Json_file::where('campaign_id', $campaign->id)
                ->where('status', 'importation_en_cours')
                ->exists();

            if (!$remaining) {
                $campaign->update(['status' => 'imported', 'progress' => 100]);
            } else {
                // on conserve importing; progression éventuelle à gérer ailleurs si tu as un compteur global connu
                $campaign->update(['status' => 'importing', 'progress' => min(99, $campaign->progress)]);
            }

            Log::info("Import partiel OK: fichier='{$filePath}' — lus={$processedThisRun}, importés={$importedThisRun}, invalides={$skippedInvalid} (offset final={$jf->nbre_lignes_traitees})");

            $summary['processed_files'][] = [
                'json_file_id' => $jf->id,
                'file'         => $filePath,
                'processed'    => $processedThisRun,
                'imported'     => $importedThisRun,
                'skipped'      => $skippedInvalid,
                'offset_final' => $jf->nbre_lignes_traitees,
                'status'       => $jf->status,
                'campaign_id'  => $campaign->id,
                'table'        => $tableName,
            ];

        } catch (\Throwable $e) {
            Log::error("Erreur import partiel fichier='{$filePath}' (campagne #{$campaign->id}) : ".$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            // on NE change PAS le status du fichier (il sera retenté au prochain cron)
            $summary['processed_files'][] = [
                'json_file_id' => $jf->id,
                'file'   => $filePath,
                'status' => 'failed_this_run',
                'error'  => $e->getMessage(),
            ];
        } finally {
            if (is_resource($stream)) fclose($stream);
        }
    }

    return response()->json($summary);
})->name('cron.import-json-files');
