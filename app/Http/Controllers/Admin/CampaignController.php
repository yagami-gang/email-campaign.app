<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Template;
use App\Models\SmtpServer;
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

        return view('pages.campaigns.edit', compact('campaign', 'templates', 'smtpServers', 'jsonFiles'));
    }

    /**
     * Met à jour une campagne existante dans la base de données.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Campaign  $campaign
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Campaign $campaign)
    {
        $validatedData = $request->validate([
            'json_file_path' => 'required|string', // Le chemin du fichier est attendu
            'smtp_server_ids' => 'required|array',
            'smtp_server_ids.*' => 'exists:smtp_servers,id',
            'sender_name' => 'required|string|max:255',
            'sender_email' => 'required|email|max:255',
            'send_frequency_minutes' => 'nullable|integer|min:1',
            'max_daily_sends' => 'nullable|integer|min:1',
            'scheduled_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        if ($campaign->status !== 'pending') {
            return response()->json(['error' => 'Impossible de modifier une campagne qui n\'est pas en attente.'], 403);
        }

        DB::beginTransaction();
        try {
            // Créer la table des contacts dynamiquement
            $tableName = 'contacts_' . time();
            Schema::create($tableName, function (Blueprint $table) {
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
                $table->timestamps();
            });

            // Lire le fichier pour compter les contacts
            $fullFilePath = Storage::disk('local')->path($validatedData['json_file_path']);
            $totalContacts = 0;

            try {
                $stream = fopen($fullFilePath, 'r');
                $contacts = Items::fromStream($stream);
                $totalContacts = iterator_count($contacts);
                fclose($stream);
            } catch (\Exception $e) {
                Log::error("Échec de la lecture du fichier pour compter les contacts: " . $e->getMessage());
                $totalContacts = 0;
            }

            // Mettre à jour la campagne avec le nom de la table et le nombre de contacts
            $campaign->update([
                'nom_table_contact' => $tableName,
                'nbre_contacts' => $totalContacts,
            ]);

            // Synchroniser les serveurs SMTP
            $smtpServerData = [];
            foreach ($request->input('smtp_server_ids', []) as $smtpServerId) {
                $smtpServerData[$smtpServerId] = [
                    'sender_name' => $validatedData['sender_name'],
                    'sender_email' => $validatedData['sender_email'],
                    'send_frequency_minutes' => $validatedData['send_frequency_minutes'],
                    'max_daily_sends' => $validatedData['max_daily_sends'],
                    'scheduled_at' => $validatedData['scheduled_at'],
                    'status' => 'pending',
                    'progress' => 0,
                    'nbre_contacts' => 0,
                ];
            }
            $campaign->smtpServers()->sync($smtpServerData);

            // Dispatcher le job d'importation des contacts avec le chemin et le nom de la table
            ProcessCampaignImport::dispatch($campaign->id, $fullFilePath, $totalContacts, $tableName);

            DB::commit();

            return response()->json(['message' => 'La configuration de la campagne est terminée et l\'importation des contacts a été mise en file d\'attente.'], 202);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la mise à jour de la campagne : ' . $e->getMessage());
            return response()->json(['error' => 'Une erreur est survenue lors de la mise à jour de la campagne. Veuillez réessayer.'], 500);
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
