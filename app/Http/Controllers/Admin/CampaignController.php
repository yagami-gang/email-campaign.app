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
        return view('pages.campaigns.index', compact('campaigns'));
    }

    /**
     * Affiche le formulaire pour créer une nouvelle campagne.
     */
    public function create()
    {
        $templates = Template::where('is_active', true)->get();
        $smtpServers = SmtpServer::where('is_active', true)->get();
        $mailingLists = MailingList::all(); // Vous devrez choisir comment lier une mailing list à une campagne
        
        return view('pages.campaigns.create', compact('templates', 'smtpServers', 'mailingLists'));
    }

    /**
     * Stocke une nouvelle campagne dans la base de données.
     *
     * @param  \App\Http\Requests\Admin\StoreCampaignRequest  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreCampaignRequest $request)
    {
        $validated = $request->validated();

        // Création minimale (étape 1)
        $campaign = Campaign::create([
            'name'          => $validated['name'],
            'subject'       => $validated['subject'],
            'template_id'   => $validated['template_id'],
            'status'        => 'pending',
            'progress'      => 0,
            'nbre_contacts' => $validated['nbre_contacts'],       // pas saisi à l'étape 1
        ]);

        // 👉 redirection vers l’étape 2 (edit)
        return redirect()
            ->route('admin.campaigns.edit', $campaign->id)
            ->with('status', 'La campagne a été créée. Complète la configuration des serveurs SMTP.');
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

        $selectedSmtpServers = $campaign->smtpServers->pluck('id')->toArray();
        
        return view('pages.campaigns.edit', compact('campaign', 'templates', 'smtpServers', 'mailingLists', 'selectedSmtpServers'));
    }

    /**
     * Met à jour une campagne existante dans la base de données.
     *
     * @param  \App\Http\Requests\Admin\UpdateCampaignRequest  $request
     * @param  \App\Models\Campaign  $campaign
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Campaign $campaign)
    {
        // RÈGLES
        $statusValues = ['draft','scheduled','running','paused','completed','failed'];

        $validator = Validator::make($request->all(), [
            // Champs "campagne"
            'name'        => ['required','string','max:255'],
            'subject'     => ['required','string','max:255'],
            'template_id' => ['required','exists:templates,id'],

            // Lignes pivot (table campaign_smtp_server)
            'smtp_rows'   => ['nullable','array'],
            'smtp_rows.*.smtp_server_id'       => ['required','exists:smtp_servers,id','distinct'], // 1 serveur = 1 ligne
            'smtp_rows.*.sender_name'          => ['nullable','string','max:255'],
            'smtp_rows.*.sender_email'         => ['nullable','email','max:255'],
            'smtp_rows.*.send_frequency_minutes'=> ['nullable','integer','min:1'],
            'smtp_rows.*.max_daily_sends'      => ['nullable','integer','min:1'],
            'smtp_rows.*.scheduled_at'         => ['nullable','date'], // "Y-m-d H:i:s" ou "Y-m-d\TH:i" accepté
            'smtp_rows.*.status'               => ['nullable', Rule::in($statusValues)],
            'smtp_rows.*.progress'             => ['nullable','integer','min:0','max:100'],
            'smtp_rows.*.nbre_contacts'        => ['nullable','integer','min:0'],
        ], [
            'smtp_rows.*.smtp_server_id.distinct' => 'Chaque serveur SMTP ne peut être sélectionné qu’une seule fois.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        // Normalisation: transforme "" en null sur les champs pivot
        $smtpRows = collect($data['smtp_rows'] ?? [])->map(function(array $row){
            foreach (['sender_name','sender_email','send_frequency_minutes','max_daily_sends','scheduled_at','status','progress','nbre_contacts'] as $k) {
                if (array_key_exists($k, $row) && $row[$k] === '') {
                    $row[$k] = null;
                }
            }
            return $row;
        });

        DB::transaction(function () use ($campaign, $data, $smtpRows) {
            // 1) Mettre à jour la campagne
            $campaign->update([
                'name'        => $data['name'],
                'subject'     => $data['subject'],
                'template_id' => $data['template_id'],
                // status/progress/nbre_contacts de la campagne ne sont pas édités ici (ils existent au pivot)
            ]);

            // 2) Préparer le tableau pour sync()
            //    Format attendu: [ smtp_server_id => [pivotCols...] ]
            $sync = [];
            foreach ($smtpRows as $row) {
                $sync[(int)$row['smtp_server_id']] = [
                    'sender_name'            => $row['sender_name'] ?? null,
                    'sender_email'           => $row['sender_email'] ?? null,
                    'send_frequency_minutes' => $row['send_frequency_minutes'] ?? null,
                    'max_daily_sends'        => $row['max_daily_sends'] ?? null,
                    'scheduled_at'           => $row['scheduled_at'] ?? null,
                    'status'                 => $row['status'] ?? null,
                    'progress'               => $row['progress'] ?? null,
                    'nbre_contacts'          => $row['nbre_contacts'] ?? null,
                ];
            }

            // 3) Sync complet (ajoute, met à jour, supprime ce qui n'est plus présent)
            $campaign->smtpServers()->sync($sync);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Campagne mise à jour avec succès.',
                'campaign_id' => $campaign->id,
                'pivot_count' => $campaign->smtpServers()->count(),
            ]);
        }

        return back()->with('status', 'Campagne et liaisons SMTP mises à jour.');
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
