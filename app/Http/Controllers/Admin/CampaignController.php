<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Template;
use App\Models\SmtpServer;
use App\Models\Json_file;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessCampaignEmails;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Exception;
use App\Jobs\ProcessCampaignImport;
use Illuminate\Validation\ValidationException;
use JsonMachine\Items;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\File;

class CampaignController extends Controller
{
    /**
     * Affiche une liste de toutes les campagnes.
     */
    public function index()
    {
        $campaigns = Campaign::with(['template', 'smtpServers'])->get();
        return view('pages.campaigns.index', compact('campaigns'));
    }

    /**
     * Affiche le formulaire pour créer une nouvelle campagne.
     */
    public function create()
    {
        $templates = Template::where('is_active', true)->get();
        $smtpServers = SmtpServer::where('is_active', true)->get();

        return view('pages.campaigns.create', compact('templates', 'smtpServers'));
    }

    /**
     * Stocke une nouvelle campagne dans la base de données.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'template_id' => 'required|exists:templates,id',
        ]);

        try {
            $campaign = Campaign::create([
                'name' => $validatedData['name'],
                'subject' => $validatedData['subject'],
                'template_id' => $validatedData['template_id'],
                'status' => 'pending',
                'progress' => 0,
                'nbre_contacts' => 0,
            ]);

        } catch (Exception $e) {
            Log::error("Erreur lors de la création de la campagne: " . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Erreur lors de la création de la campagne : ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.campaigns.edit', $campaign->id)
            ->with('status', 'La campagne a été créée. Importez le fichier JSON et configurez les serveurs SMTP pour l\'ajouter.');
    }

    /**
     * Affiche les détails d'une campagne spécifique.
     */
    public function show(Campaign $campaign)
    {
        return view('pages.campaigns.show', compact('campaign'));
    }

    /**
     * Affiche le formulaire pour éditer une campagne existante et liste les fichiers JSON disponibles.
     */
    public function edit(Campaign $campaign)
    {
        $templates = Template::where('is_active', true)->get();
        $smtpServers = SmtpServer::where('is_active', true)->get();

        // Récupère la liste des fichiers JSON dans le dossier d'importation
        $jsonFiles = Storage::disk('local')->files('private');

        $jsonFiles = collect(File::files(storage_path('app/private'))) // non récursif
    ->filter(fn ($f) => $f->getExtension() === 'json')
    ->map(fn ($f) => $f->getPathname())
    ->all();

        //dd($files);

        return view('pages.campaigns.edit', compact('campaign', 'templates', 'smtpServers', 'jsonFiles'));
    }

    /**
     * Met à jour une campagne existante dans la base de données.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Campaign  $campaign
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Met à jour une campagne, synchronise les serveurs SMTP et lance l'importation.
     * C'est le cœur de l'étape 2.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Campaign  $campaign
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Campaign $campaign)
    {
        // Valider les champs de base et les tableaux dynamiques
        $validated = $request->validate([
            // Section 1: Infos générales
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'template_id' => 'required',

            // Section 2: Canaux d'envoi (API)
            'smtp_rows' => 'required|array|min:1',
            'smtp_rows.*.sender_name' => 'required|string|max:255',
            'smtp_rows.*.sender_email' => 'required|email|max:255',
            'smtp_rows.*.smtp_server_id' => 'required',
            'smtp_rows.*.send_frequency_minutes' => 'nullable|integer|min:1',
            'smtp_rows.*.max_daily_sends' => 'nullable|integer|min:1',
            'smtp_rows.*.scheduled_at' => 'nullable|date_format:Y-m-d\TH:i',

            // Section 3: Fichiers de contacts
            'json_file_path' => 'required|array|min:1',
            'json_file_path.*' => [
                'required',
                'string'
            ],
        ]);

        DB::beginTransaction();
        try {
            // 1. Mettre à jour les informations de base de la campagne
            $campaign->update([
                'name' => $validated['name'],
                'subject' => $validated['subject'],
                'template_id' => $validated['template_id']
            ]);

            foreach($validated['json_file_path'] as $json_file_path){
                if( Json_file::where('file_path', $json_file_path)->where('campaign_id', $campaign->id)->count() == 0 ){
                    $json_f = new Json_file;
                    $json_f->file_path = $json_file_path;
                    $json_f->campaign_id = $campaign->id;
                    $json_f->save();
                }
            }

            // 2. Préparer les données pour la synchronisation de la table pivot
            $smtpSyncData = [];
            foreach ($validated['smtp_rows'] as $row) {
                // S'assurer qu'un serveur n'est pas ajouté plusieurs fois
                if (isset($smtpSyncData[$row['smtp_server_id']])) {
                    continue;
                }
                $smtpSyncData[$row['smtp_server_id']] = [
                    'sender_name' => $row['sender_name'],
                    'sender_email' => $row['sender_email'],
                    'send_frequency_minutes' => $row['send_frequency_minutes'],
                    'max_daily_sends' => $row['max_daily_sends'],
                    'scheduled_at' => $row['scheduled_at'],
                ];
            }
            $campaign->smtpServers()->sync($smtpSyncData);

            // 3. Gestion de la table de contacts
            
            $tableName = $campaign->nom_table_contact;

            DB::commit();

            if ( $campaign->nom_table_contact == null || $campaign->nom_table_contact == "" ){

                $tableName = 'contacts_'.time();

                // Créer la nouvelle table
                Schema::create($tableName, function ($table) {
                    // Définissez ici la structure exacte de votre table de contacts
                    $table->id();
                    $table->string('email')->unique();
                    $table->string('name')->nullable();
                    $table->string('firstname')->nullable();
                    $table->string('cp')->nullable();
                    $table->string('department')->nullable();
                    $table->string('phone_number')->nullable();
                    $table->string('city')->nullable();
                    $table->string('profession')->nullable();
                    $table->string('habitation')->nullable();
                    $table->string('anciennete')->nullable();
                    $table->string('statut')->nullable();
                    //$table->timestamps();
                    $table->timestamp('imported_at')->useCurrent();
                });
            }

            // Mettre à jour la campagne avec le nom de sa table de contacts
            $new_status = $campaign->status;
            $progress = $campaign->progress;
            $nbre_contacts = $campaign->nbre_contacts;

            if( $campaign->status == "pending" ){
                $new_status = 'importing';
                $progress = 0;
                $nbre_contacts = 0;
            }
            
            // et réinitialiser la progression.
            $campaign->update([
                'nom_table_contact' => $tableName,
                'status' => $new_status, // La campagne est maintenant 'planifiée'
                'progress' => $progress,
                'nbre_contacts' => $nbre_contacts, // Le job mettra à jour ce compteur
            ]);

            // 4. Lancer le job d'importation en arrière-plan
            //ProcessCampaignImport::dispatch($campaign->id, $validated['json_file_path'], $tableName);

            return redirect()
                ->route('admin.campaigns.index')
                ->with('status', "La campagne '{$campaign->name}' a été configurée avec succès. L'importation des contacts a démarré en arrière-plan.");

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de la mise à jour de la campagne #{$campaign->id}: " . $e->getMessage());
            return redirect()->back()->withInput()->withErrors(['error' => 'Une erreur interne est survenue. Veuillez réessayer.']);
        }
    }

    /**
     * Supprime une campagne de la base de données, y compris la table de contacts associée.
     */
    public function destroy(Campaign $campaign, Request $request)
    {
        try {
            DB::beginTransaction();

            if ($campaign->nom_table_contact && Schema::hasTable($campaign->nom_table_contact)) {
                Schema::dropIfExists($campaign->nom_table_contact);
            }

            $campaign->delete();

            DB::commit();

            if ($request->expectsJson()) {
                return Response::json(['message' => 'La campagne a été supprimée avec succès !'], 200);
            }
            return redirect()->route('admin.campaigns.index')->with('success', 'La campagne a été supprimée avec succès !');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de la suppression de la campagne ID {$campaign->id}: " . $e->getMessage());
            if ($request->expectsJson()) {
                return Response::json(['error' => 'Erreur lors de la suppression de la campagne.', 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Erreur lors de la suppression de la campagne.');
        }
    }

    /**
     * Action pour lancer une campagne.
     */
    public function launch(Campaign $campaign, Request $request)
    {
        if ($campaign->status === 'pending' || $campaign->status === 'paused') {
            $hasSmtpServers = $campaign->smtpServers->isNotEmpty();
            $hasContacts = $campaign->nom_table_contact && Schema::hasTable($campaign->nom_table_contact) && DB::table($campaign->nom_table_contact)->count() > 0;

            if (!$hasSmtpServers || !$hasContacts) {
                $errorMessage = 'Impossible de lancer la campagne : elle doit avoir au moins un serveur API et des contacts associés.';
                if ($request->expectsJson()) {
                    return Response::json(['error' => $errorMessage], 400);
                }
                return redirect()->back()->with('error', $errorMessage);
            }

            $campaign->update(['status' => 'active', 'progress' => 0]);
            ProcessCampaignEmails::dispatch($campaign->id);
            $message = 'La campagne a été lancée et est maintenant active. Les emails seront envoyés en arrière-plan.';

            if ($request->expectsJson()) {
                return Response::json([
                    'message' => $message,
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status,
                    'progress' => $campaign->progress
                ], 202);
            }

            return redirect()->back()->with('success', $message);
        }

        $message = 'La campagne ne peut pas être lancée dans son état actuel.';
        if ($request->expectsJson()) {
            return Response::json(['error' => $message], 400);
        }
        return redirect()->back()->with('error', $message);
    }

    /**
     * Action pour mettre en pause une campagne.
     */
    public function pause(Campaign $campaign, Request $request)
    {
        if ($campaign->status === 'active') {
            $campaign->update(['status' => 'paused']);
            $message = 'La campagne a été mise en pause.';
            if ($request->expectsJson()) {
                return Response::json([
                    'message' => $message,
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status,
                    'progress' => $campaign->progress
                ], 200);
            }
            return redirect()->back()->with('success', $message);
        }
        $message = 'La campagne ne peut pas être mise en pause dans son état actuel.';
        if ($request->expectsJson()) {
            return Response::json(['error' => $message], 400);
        }
        return redirect()->back()->with('error', $message);
    }

    /**
     * Action pour reprendre une campagne.
     */
    public function resume(Campaign $campaign, Request $request)
    {
        if ($campaign->status === 'paused') {
            $hasSmtpServers = $campaign->smtpServers->isNotEmpty();
            $hasContacts = $campaign->nom_table_contact && Schema::hasTable($campaign->nom_table_contact) && DB::table($campaign->nom_table_contact)->count() > 0;

            if (!$hasSmtpServers || !$hasContacts) {
                $errorMessage = 'Impossible de reprendre la campagne : elle doit avoir au moins un serveur API et des contacts associés.';
                if ($request->expectsJson()) {
                    return Response::json(['error' => $errorMessage], 400);
                }
                return redirect()->back()->with('error', $errorMessage);
            }

            $campaign->update(['status' => 'active']);
            ProcessCampaignEmails::dispatch($campaign->id);
            $message = 'La campagne a été reprise et est maintenant active. Les emails reprendront leur envoi en arrière-plan.';
            if ($request->expectsJson()) {
                return Response::json([
                    'message' => $message,
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status,
                    'progress' => $campaign->progress
                ], 202);
            }
            return redirect()->back()->with('success', $message);
        }
        $message = 'La campagne ne peut pas être reprise dans son état actuel.';
        if ($request->expectsJson()) {
            return Response::json(['error' => $message], 400);
        }
        return redirect()->back()->with('error', $message);
    }

    /**
     * Récupère le statut et la progression d'une campagne.
     */
    public function getSendProgress(int $id): \Illuminate\Http\JsonResponse
    {
        $campaign = Campaign::find($id);

        if (!$campaign) {
            return Response::json(['error' => 'Campagne introuvable.'], 404);
        }

        return Response::json([
            'campaign_id' => $campaign->id,
            'status' => $campaign->status,
            'progress' => $campaign->progress
        ]);
    }
}
