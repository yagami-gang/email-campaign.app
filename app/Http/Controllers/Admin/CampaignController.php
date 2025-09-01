<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Template;
use App\Models\SmtpServer;
use App\Models\MailingList;
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

class CampaignController extends Controller
{
    /**
     * Affiche une liste de toutes les campagnes.
     */
    public function index()
    {
        $campaigns = Campaign::with(['template', 'smtpServers', 'mailingLists'])->get();
        return view('pages.campaigns.index', compact('campaigns'));
    }

    /**
     * Affiche le formulaire pour créer une nouvelle campagne.
     */
    public function create()
    {
        $templates = Template::where('is_active', true)->get();
        $smtpServers = SmtpServer::where('is_active', true)->get();
        $mailingLists = MailingList::all();

        return view('pages.campaigns.create', compact('templates', 'smtpServers', 'mailingLists'));
    }

    /**
     * Stocke une nouvelle campagne dans la base de données.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'template_id' => 'required|exists:templates,id',
        ]);

        try {
            // Créer la campagne avec le statut 'pending'
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
            ->with('status', 'La campagne a été créée. Importez le fichier JSON pour ajouter les contacts.');
    }

    /**
     * Affiche les détails d'une campagne spécifique.
     */
    public function show(Campaign $campaign)
    {
        return view('pages.campaigns.show', compact('campaign'));
    }

    /**
     * Affiche le formulaire pour éditer une campagne existante.
     */
    public function edit(Campaign $campaign)
    {
        $templates = Template::where('is_active', true)->get();
        $smtpServers = SmtpServer::where('is_active', true)->get();
        $mailingLists = MailingList::all();

        return view('pages.campaigns.edit', compact('campaign', 'templates', 'smtpServers', 'mailingLists'));
    }

    /**
     * Met à jour une campagne existante dans la base de données.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Campaign  $campaign
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Campaign $campaign)
    {
        try {
            $request->validate([
                'json_file' => 'required|file|mimes:json',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Fichier JSON requis.'], 422);
        }

        // Vérifier que la campagne est bien en statut 'pending' pour être mise à jour
        if ($campaign->status !== 'pending') {
            return response()->json(['error' => 'Impossible de modifier une campagne qui n\'est pas en attente.'], 403);
        }

        $filePath = $request->file('json_file')->store('imports');

        $totalContacts = 0;
        try {
            $stream = Storage::disk('local')->readStream($filePath);
            $contacts = Items::fromStream($stream);
            $totalContacts = iterator_count($contacts);
        } catch (\Exception $e) {
            Log::error("Échec de la lecture du fichier pour compter les contacts: " . $e->getMessage());
            $totalContacts = 0;
        }

        $campaign->update(['nbre_contacts' => $totalContacts, 'status' => 'pending']);

        ProcessCampaignImport::dispatch($campaign->id, $filePath, $totalContacts);

        Log::info("Job d'importation de contacts déclenché pour la campagne ID {$campaign->id}.");
        return response()->json(['message' => 'L\'importation des contacts a été mise en file d\'attente et sera traitée en arrière-plan.'], 202);
    }

    /**
     * Supprime une campagne de la base de données.
     */
    public function destroy(Campaign $campaign, Request $request)
    {
        try {
            $campaign->delete();
            if ($request->expectsJson()) {
                return Response::json(['message' => 'La campagne a été supprimée avec succès !'], 200);
            }
            return redirect()->route('admin.campaigns.index')->with('success', 'La campagne a été supprimée avec succès !');
        } catch (\Exception $e) {
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
            if ($campaign->smtpServers->isEmpty() || $campaign->contacts->isEmpty()) {
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
            if ($campaign->smtpServers->isEmpty() || $campaign->contacts->isEmpty()) {
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
