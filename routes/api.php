<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonMachine\Items;

use App\Models\Campaign;
use App\Models\Json_file; // adapte si ton modèle a un autre nom/namespace

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;


/**
 * Lance l'importation des contacts des Json_files au statut "importation_en_cours"
 */

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





////////////////////////////////////////////////////////////////////////////////


/**
 * Lance l'envoi des mails pour toutes les campagnes au statut "imported" et "active"
 */


Route::get('/api/cron/send-campaign-emails', function (Request $request) {
    // (Optionnel) Protéger la route:
    // if ($request->header('X-CRON-TOKEN') !== env('CRON_TOKEN')) abort(403);

    @set_time_limit(0);

    // Limites
    $PER_RUN_LIMIT   = 50; // nb max de contacts traités par Serveurs API et par campagne à CHAQUE passage
    $BATCH_API_SIZE  = 10;  // taille d’un paquet envoyé à l’API distante

    // Récupère toutes les campagnes prêtes
    $campaigns = Campaign::with(['apiEndpoints', 'template'])
        ->where('status', 'imported')
        ->orWhere('status', 'active')
        ->get();

    $report = [
        'campaigns_checked' => $campaigns->count(),
        'campaigns_started' => 0,
        'details' => [],
    ];

    foreach ($campaigns as $campaign) {
        if ($campaign->apiEndpoints->isEmpty()) {
            Log::warning("Campagne #{$campaign->id} sans serveurs SMTP. Passage.");
            $campaign->update([
                'status'        => 'failed',
                'error_message' => "Serveur API absent"
            ]);
            continue;
        }

        $contactsTable = $campaign->nom_table_contact;

        if( $contactsTable == null ){
            $campaign->update([
                'status'        => 'failed',
                'error_message' => "table contacts absente"
            ]);
            continue;
        }

        // si le nombre limit de shoot est atteint pour une campagne
        $count = DB::table($contactsTable)
            ->where('status', 'sended')
            ->count();

        if ($count >= $campaign->shoot_limit && $campaign->shoot_limit > 0) {
            DB::table('campaigns')->where('id', $campaign->id)->update([
                'status'   => 'completed',
                'progress' => 100
            ]);
            Log::warning("Campagne #{$campaign->id} a atteint son shoot_limit");
            continue;
        }

        // Passe en active si besoin
        if ($campaign->status == 'imported') {
            $campaign->update([
                'status'     => 'active',
                'progress'   => 0,
                'sent_count' => 0,
            ]);
        }

        $report['campaigns_started']++;


        $today = Carbon::today();

        foreach ($campaign->apiEndpoints as $apiEndpoint) {
            $p = $apiEndpoint->pivot; // sender_email, sender_name, scheduled_at, max_daily_sends, ...

            // Respect de la date de départ (scheduled_at)
            if (!empty($p->scheduled_at)) {
                $scheduledAt = Carbon::parse($p->scheduled_at);
                if ($scheduledAt->isFuture()) {
                    $report['details'][] = [
                        'campaign_id' => $campaign->id,
                        'smtp_id'     => $apiEndpoint->id,
                        'status'      => 'scheduled_later',
                        'scheduled_at'=> $scheduledAt->toDateTimeString(),
                    ];
                    continue;
                }
            }

            if ( in_array($p->status, ['failed', 'paused', 'canceled']) ) {
                $report['details'][] = [
                    'campaign_id' => $campaign->id,
                    'smtp_id'     => $apiEndpoint->id,
                    'status'      => ($p->status=='failed' ? 'failed : '.$p->error_message : $p->status),
                ];
                continue;
            }

            // Quota journalier pour ce SMTP
            $dailySent = DB::table($contactsTable)
                ->whereDate('sent_at', $today)
                ->where('api_endpoint_id', $apiEndpoint->id)
                ->where('status', 'sended')
                ->count();

            $dailyRemain = ($p->max_daily_sends && $p->max_daily_sends > 0)
                ? max(0, $p->max_daily_sends - $dailySent)
                : PHP_INT_MAX;

            if ($dailyRemain <= 0) {
                $report['details'][] = [
                    'campaign_id' => $campaign->id,
                    'smtp_id'     => $apiEndpoint->id,
                    'status'      => 'daily_quota_reached',
                ];
                continue;
            }

            // Nombre de contacts à prendre pour CE PASSAGE
            $PER_RUN_LIMIT = $p->send_frequency_minutes ?? $PER_RUN_LIMIT;
            $toSend = min($PER_RUN_LIMIT, $dailyRemain);
            if ($toSend <= 0) {
                $report['details'][] = [
                    'campaign_id' => $campaign->id,
                    'smtp_id'     => $apiEndpoint->id,
                    'status'      => 'nothing_to_send',
                ];
                continue;
            }

            // Contacts restants: status NULL et pas blacklistés
            $contacts = DB::table($contactsTable . ' as c')
                ->select('c.*')
                ->leftJoin('blacklist as b', 'c.email', '=', 'b.email')
                ->whereNull('b.id')
                //->whereNull('c.status')
                ->where(function($q){
                    $q->whereNull('c.status')->orWhere('c.status', 'fail_http');
                })
                ->limit($toSend)
                ->get();

            if ($contacts->isEmpty()) {
                // plus aucun contact trouvé
                //il faut cloturer ce pivot

                $report['details'][] = [
                    'campaign_id' => $campaign->id,
                    'smtp_id'     => $apiEndpoint->id,
                    'status'      => 'no_remaining_contacts',
                ];

                continue;
            }

            $sentOk = 0;
            $sentFail = 0;

            $payload = [];

            // On découpe en paquets de 10
            foreach ($contacts->chunk($BATCH_API_SIZE) as $chunk) {
                // Construit le payload du paquet
                $messages = [];
                foreach ($chunk as $contact) {
                    // Personnalisation simple (tu peux enrichir selon tes placeholders)
                    $html = $campaign->template ? $campaign->template->html_content : '';
                    $html = str_replace(
                        ['{{name}}','{{firstname}}','{{email}}','{{city}}','{{cp}}','{{department}}','{{phoneNumber}}','{{profession}}','{{habitation}}','{{anciennete}}','{{statut}}'],
                        [
                            $contact->name ?? '', $contact->firstname ?? '', $contact->email ?? '',
                            $contact->city ?? '', $contact->cp ?? '', $contact->department ?? '',
                            $contact->phone_number ?? '', $contact->profession ?? '', $contact->habitation ?? '',
                            $contact->anciennete ?? '', $contact->statut ?? ''
                        ],
                        $html
                    );

                     /**
                     * Ajoute le pixel de suivi d'ouverture.
                     */
                    $trackingPixelUrl = route('track.open', [
                        'contactTable' => $campaign->nom_table_contact,
                        'id_contact'   => $contact->id,
                    ]);
                    $html = $html . "<img src=\"{$trackingPixelUrl}\" alt=\"\" width=\"1\" height=\"1\" style=\"display:none;\"/>";

                    /**
                     * Traite et raccourcit les URLs pour le suivi des clics.
                     */

                    $html = preg_replace_callback('/<a[^>]*href="([^"]+)"[^>]*>/i', function($matches) use ($campaign, $contact) {
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
                            'contact_id' => $contact->id,
                        ]);

                        $trackedUrl = route('tracking.click', $shortCode);
                        return str_replace($originalUrl, $trackedUrl, $matches[0]);
                    }, $html);


                    /**
                     * Ajoute le lien de désinscription.
                     */
                    $unsubscribeUrl = route('unsubscribe.form', ['encryptedEmail' => encrypt($contact->email), 'campaign_id'=> $campaign->id,]);
                    $html = $html . "<p style='text-align:center; font-size:10px;'><a href=\"{$unsubscribeUrl}\">Se désinscrire</a></p>";



                    $messages[] = [
                        'to_email' => $contact->email,
                        'to_name'  => trim(($contact->firstname ?? '').' '.($contact->name ?? '')),
                        'subject'  => $campaign->subject,
                        'content'  => $html,
                        // Si l’API distante accepte un identifiant, tu peux ajouter:
                        // 'reference' => ['campaign_id' => $campaign->id, 'smtp_id' => $apiEndpoint->id, 'email' => $contact->email],
                    ];
                }

                $payload = [
                    'campaign_id' => $campaign->id,
                    'from_email'  => $p->sender_email,
                    'from_name'   => $p->sender_name,
                    'messages'    => $messages, // <= paquet de 10 messages
                ];

              // --- ENVOI REEL DES MAILS ---
                $resp = null;
                $ok   = false;
                $http = 0;
                $respJson = null;

                try {
                    if (empty($apiEndpoint->url)) {
                        throw new \RuntimeException("SMTP server #{$apiEndpoint->id} n'a pas d'URL API configurée.");
                    }

                    $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
                    if (!empty($apiEndpoint->api_key)) {
                        $headers['Authorization'] = 'Bearer ' . $apiEndpoint->api_key;
                    }

                    $resp     = Http::withHeaders($headers)->timeout(60)->post($apiEndpoint->url, $payload);
                    $http     = $resp->status();
                    $respJson = $resp->json(); // peut être null si pas du JSON
                    $ok       = $resp->successful(); // true pour 2xx

                } catch (\Throwable $e) {
                    Log::error("Erreur HTTP (campagne {$campaign->id}, smtp {$apiEndpoint->id}) : ".$e->getMessage());
                    $ok = false;
                    $http = 0;
                    $respJson = null;
                }

                // Emails du chunk (pour bornage sécurisé)
                $emailsInChunk = $chunk->pluck('email')->all();

                /**
                 * 2) Traitement quand l’appel est 2xx et renvoie un tableau 'results'
                 *    → updates par email selon la ligne de résultat.
                 */
                if ($ok && is_array($respJson) && isset($respJson['results']) && is_array($respJson['results'])) {

                    $results = collect($respJson['results']);
                    $sentForChunk = 0;

                    // a) Mettre à jour chaque email retourné par l’API
                    foreach ($results as $row) {
                        $to   = $row['to_email'] ?? null;
                        $stat = $row['status']   ?? null;
                        $err  = $row['error']    ?? null;

                        if (!$to || !in_array($to, $emailsInChunk, true)) {
                            continue; // ne met pas à jour hors chunk
                        }

                        if ($stat === 'sent') {
                            DB::table($contactsTable)
                                ->where('email', $to)
                                ->update([
                                    'status'          => 'sended',
                                    'sent_at'         => now(),
                                    'delivered_at'    => now(),
                                    'api_endpoint_id'  => $apiEndpoint->id,
                                ]);
                            $sentForChunk++;
                            $sentOk++;
                        } elseif ($stat === 'failed') {
                            DB::table($contactsTable)
                                ->where('email', $to)
                                ->update([
                                    'status'          => 'fail_smtp',
                                    'error_message'   => Str::limit((string)$err, 250),
                                    'api_endpoint_id'  => $apiEndpoint->id,
                                ]);
                            $sentFail++;
                        }
                    }

                    // b) Si certains emails du chunk ne sont pas revenus dans 'results',
                    //    on les marque en échec HTTP (sécurité)
                    $returned = $results->pluck('to_email')->filter()->unique()->all();
                    $missing  = array_values(array_diff($emailsInChunk, $returned));
                    if (!empty($missing)) {
                        DB::table($contactsTable)
                            ->whereIn('email', $missing)
                            ->update([
                                'status'          => 'fail_http',
                                'error_message'   => 'Missing in API results',
                                'api_endpoint_id'  => $apiEndpoint->id,
                            ]);
                        $sentFail += count($missing);
                    }

                    // c) Incrémenter sent_count UNIQUEMENT sur les emails 'sent'
                    if ($sentForChunk > 0) {
                        DB::table('campaigns')->where('id', $campaign->id)->increment('sent_count', $sentForChunk);
                        $newSent = (int) DB::table('campaigns')->where('id', $campaign->id)->value('sent_count');
                        if ($campaign->nbre_contacts > 0) {
                            $progress = (int) floor(($newSent / $campaign->nbre_contacts) * 100);
                            DB::table('campaigns')->where('id', $campaign->id)->update([
                                'progress' => min(99, $progress),
                            ]);
                        }
                    }

                } else {
                    /**
                     * 3) Appel non 2xx (ou payload non conforme) → marquer tout le chunk en fail_http.
                     *    (Tu as quand même le pivot marqué 'failed' si http=400/401/403, plus haut.)
                     */

                    $errMsg = $resp ? $resp->body() : "Appel API échoué (HTTP {$http})";

                    if (in_array($http, [400, 401, 403], true)) {
                        $errMsg = is_array($respJson) ? ($respJson['error'] ?? $resp->body()) : ($resp ? $resp->body() : "Appel API échoué (HTTP {$http})");
                        $errMsg = "Code HTTP: {$http} - ".$errMsg;
                    }


                    DB::table($contactsTable)
                        ->whereIn('email', $emailsInChunk)
                        ->update([
                            'sent_at'         => now(),
                            'status'          => 'fail_http',
                            'error_message'   => Str::limit($errMsg, 250),
                            'api_endpoint_id'  => $apiEndpoint->id,
                        ]);


                    DB::table('campaign_smtp_server')
                        ->where('campaign_id', $campaign->id)
                        ->where('api_endpoint_id', $apiEndpoint->id)
                        ->update([
                            'status'        => 'failed',
                            'error_message' => Str::limit($errMsg, 250),
                            'updated_at'    => now(),
                        ]);



                    $sentFail += count($emailsInChunk);

                    if ($resp && !$ok) {
                        Log::warning("Envoi batch échoué (HTTP {$http}) - campaign({$campaign->id}) - smtp({$apiEndpoint->id}) " . $bodySnippet);
                    }
                }
            }

            $report['details'][] = [
                'campaign_id' => $campaign->id,
                'smtp_id'     => $apiEndpoint->id,
                'attempted'   => $contacts->count(),
                'sent_ok'     => $sentOk,
                'sent_fail'   => $sentFail,
                'batches'     => (int) ceil(max(1, $contacts->count()) / $BATCH_API_SIZE),
            ];
            $reports['payloads'][] = $payload;
        }

        // Si plus aucun contact restant (status NULL et non blacklisté) -> completed
        $remaining = DB::table($contactsTable . ' as c')
            ->leftJoin('blacklist as b', 'c.email', '=', 'b.email')
            ->whereNull('b.id')
            //->whereNull('c.status')
            ->where(function($q){
                $q->whereNull('c.status')->orWhere('c.status', 'fail_http');
            })
            ->exists();

        if (!$remaining) {
            DB::table('campaigns')->where('id', $campaign->id)->update([
                'status'   => 'completed',
                'progress' => 100
            ]);
        }
    }

    return response()->json($report);
})->name('cron.send-campaign-emails');
