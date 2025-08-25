<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Template;
use App\Models\SmtpServer;
use App\Models\MailingList;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Http\Requests\Admin\UpdateCampaignRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessCampaignEmails;
use Illuminate\Support\Facades\Response; // Importation pour les réponses JSON

class CampaignController extends Controller
{
    /**
     * Affiche une liste de toutes les campagnes.
     */
    public function index()
    {
        $campaigns = Campaign::with(['template', 'smtpServers'])->get();
        return view('admin.campaigns.index', compact('campaigns'));
    }

    /**
     * Affiche le formulaire pour créer une nouvelle campagne.
     */
    public function create()
    {
        $templates = Template::where('is_active', true)->get();
        $smtpServers = SmtpServer::where('is_active', true)->get();
        $mailingLists = MailingList::all(); // Vous devrez choisir comment lier une mailing list à une campagne
        
        return view('admin.campaigns.create', compact('templates', 'smtpServers', 'mailingLists'));
    }

    /**
     * Stocke une nouvelle campagne dans la base de données.
     *
     * @param  \App\Http\Requests\Admin\StoreCampaignRequest  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreCampaignRequest $request)
    {
        DB::beginTransaction();
        try {
            $validatedData = $request->validated();

            $campaign = Campaign::create([
                'name' => $validatedData['name'],
                'subject' => $validatedData['subject'],
                'sender_name' => $validatedData['sender_name'],
                'sender_email' => $validatedData['sender_email'],
                'send_frequency_minutes' => $validatedData['send_frequency_minutes'] ?? 0,
                'max_daily_sends' => $validatedData['max_daily_sends'] ?? 0,
                'scheduled_at' => $validatedData['scheduled_at'],
                'template_id' => $validatedData['template_id'],
                'status' => 'pending', // Nouvelle campagne est toujours en attente
                'progress' => 0,       // Progression initiale
            ]);

            $campaign->smtpServers()->attach($validatedData['smtp_server_ids']);

            DB::commit();

            if ($request->expectsJson()) {
                return Response::json([
                    'message' => 'La campagne a été créée avec succès ! Elle est en attente.',
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status,
                    'progress' => $campaign->progress
                ], 201); // 201 Created
            }
            return redirect()->route('admin.campaigns.index')->with('success', 'La campagne a été créée avec succès ! Elle est en attente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de la création de la campagne: " . $e->getMessage());
            if ($request->expectsJson()) {
                return Response::json(['error' => 'Erreur lors de la création de la campagne.', 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Erreur lors de la création de la campagne.');
        }
    }

    /**
     * Affiche les détails d'une campagne spécifique.
     */
    public function show(Campaign $campaign)
    {
        return redirect()->route('admin.campaigns.edit', $campaign);
    }

    /**
     * Affiche le formulaire pour éditer une campagne existante.
     */
    public function edit(Campaign $campaign)
    {
        $templates = Template::where('is_active', true)->get();
        $smtpServers = SmtpServer::where('is_active', true)->get();
        $mailingLists = MailingList::all();

        $selectedSmtpServers = $campaign->smtpServers->pluck('id')->toArray();
        
        return view('admin.campaigns.edit', compact('campaign', 'templates', 'smtpServers', 'mailingLists', 'selectedSmtpServers'));
    }

    /**
     * Met à jour une campagne existante dans la base de données.
     *
     * @param  \App\Http\Requests\Admin\UpdateCampaignRequest  $request
     * @param  \App\Models\Campaign  $campaign
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign)
    {
        DB::beginTransaction();
        try {
            $validatedData = $request->validated();

            $campaign->update([
                'name' => $validatedData['name'],
                'subject' => $validatedData['subject'],
                'sender_name' => $validatedData['sender_name'],
                'sender_email' => $validatedData['sender_email'],
                'send_frequency_minutes' => $validatedData['send_frequency_minutes'] ?? 0,
                'max_daily_sends' => $validatedData['max_daily_sends'] ?? 0,
                'scheduled_at' => $validatedData['scheduled_at'],
                'template_id' => $validatedData['template_id'],
                // Le statut et la progression ne sont pas modifiés via ce formulaire
            ]);

            $campaign->smtpServers()->sync($validatedData['smtp_server_ids']);

            DB::commit();
            if ($request->expectsJson()) {
                return Response::json(['message' => 'La campagne a été mise à jour avec succès !'], 200);
            }
            return redirect()->route('admin.campaigns.index')->with('success', 'La campagne a été mise à jour avec succès !');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de la mise à jour de la campagne ID {$campaign->id}: " . $e->getMessage());
            if ($request->expectsJson()) {
                return Response::json(['error' => 'Erreur lors de la mise à jour de la campagne.', 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Erreur lors de la mise à jour de la campagne.');
        }
    }

    /**
     * Supprime une campagne de la base de données.
     *
     * @param  \App\Models\Campaign  $campaign
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Campaign $campaign)
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
     * Met à jour le statut et dispatche le Job d'envoi d'emails.
     *
     * @param Campaign $campaign
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function launch(Campaign $campaign, Request $request)
    {
        if ($campaign->status === 'pending' || $campaign->status === 'paused') {
            $campaign->update(['status' => 'active', 'progress' => 0]); // Remet la progression à 0 au lancement/reprise
            ProcessCampaignEmails::dispatch($campaign->id);
            $message = 'La campagne a été lancée et est maintenant active. Les emails seront envoyés en arrière-plan.';
            if ($request->expectsJson()) {
                return Response::json([
                    'message' => $message,
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status,
                    'progress' => $campaign->progress
                ], 202); // 202 Accepted
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
     *
     * @param Campaign $campaign
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
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
     *
     * @param Campaign $campaign
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function resume(Campaign $campaign, Request $request)
    {
        if ($campaign->status === 'paused') {
            $campaign->update(['status' => 'active']);
            ProcessCampaignEmails::dispatch($campaign->id); // On redispatch le job
            $message = 'La campagne a été reprise et est maintenant active. Les emails reprendront leur envoi en arrière-plan.';
            if ($request->expectsJson()) {
                return Response::json([
                    'message' => $message,
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status,
                    'progress' => $campaign->progress
                ], 202); // 202 Accepted
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
     * Cette méthode sera appelée par le frontend pour mettre à jour la barre de progression.
     *
     * @param int $id L'ID de la campagne.
     * @return \Illuminate\Http\JsonResponse
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
