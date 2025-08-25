<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class StatisticController extends Controller
{
    /**
     * Affiche le tableau de bord des statistiques pour toutes les campagnes.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Récupère toutes les campagnes avec leurs templates associés
        $campaigns = Campaign::with('template')->get();

        $statistics = [];

        foreach ($campaigns as $campaign) {
            // Nombre total d'emails envoyés pour cette campagne
            $totalSent = $campaign->emailLogs()->where('status', 'sent')->count();

            // Nombre d'ouvertures uniques pour cette campagne
            $totalOpens = DB::table('tracking_opens')
                            ->join('email_logs', 'tracking_opens.email_log_id', '=', 'email_logs.id')
                            ->where('email_logs.campaign_id', $campaign->id)
                            ->distinct('email_logs.contact_id') // Compter les ouvertures uniques par contact
                            ->count();

            // Nombre de clics uniques pour cette campagne
            $totalClicks = DB::table('tracking_clicks')
                             ->join('email_logs', 'tracking_clicks.email_log_id', '=', 'email_logs.id')
                             ->where('email_logs.campaign_id', $campaign->id)
                             ->distinct('email_logs.contact_id') // Compter les clics uniques par contact
                             ->count();

            // Calcul du taux d'ouverture
            $openRate = ($totalSent > 0) ? round(($totalOpens / $totalSent) * 100, 2) : 0;

            // Calcul du taux de clic
            // Le taux de clic peut être calculé par rapport aux emails envoyés ou aux emails ouverts
            // Ici, nous le calculons par rapport aux emails envoyés pour une vue globale
            $clickRate = ($totalSent > 0) ? round(($totalClicks / $totalSent) * 100, 2) : 0;
            
            // Si vous préférez le CTR par rapport aux ouvertures (Click-Through Open Rate) :
            // $clickRate = ($totalOpens > 0) ? round(($totalClicks / $totalOpens) * 100, 2) : 0;


            $statistics[] = [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'template_name' => $campaign->template->name ?? 'N/A',
                'total_sent' => $totalSent,
                'total_opens' => $totalOpens,
                'total_clicks' => $totalClicks,
                'open_rate' => $openRate, // Taux d'ouverture en %
                'click_rate' => $clickRate, // Taux de clic en %
            ];
        }

        // Renvoie la vue 'admin.statistics.index' avec les statistiques calculées
        return view('admin.statistics.index', compact('statistics'));
    }

    /**
     * Récupère les statistiques pour une campagne spécifique (peut être utilisé par AJAX).
     *
     * @param int $campaignId L'ID de la campagne.
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $campaignId): \Illuminate\Http\JsonResponse
    {
        $campaign = Campaign::with('template')->find($campaignId);

        if (!$campaign) {
            return Response::json(['error' => 'Campagne introuvable.'], 404);
        }

        $totalSent = $campaign->emailLogs()->where('status', 'sent')->count();
        $totalOpens = DB::table('tracking_opens')
                        ->join('email_logs', 'tracking_opens.email_log_id', '=', 'email_logs.id')
                        ->where('email_logs.campaign_id', $campaign->id)
                        ->distinct('email_logs.contact_id')
                        ->count();
        $totalClicks = DB::table('tracking_clicks')
                         ->join('email_logs', 'tracking_clicks.email_log_id', '=', 'email_logs.id')
                         ->where('email_logs.campaign_id', $campaign->id)
                         ->distinct('email_logs.contact_id')
                         ->count();

        $openRate = ($totalSent > 0) ? round(($totalOpens / $totalSent) * 100, 2) : 0;
        $clickRate = ($totalSent > 0) ? round(($totalClicks / $totalSent) * 100, 2) : 0;

        return Response::json([
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'template_name' => $campaign->template->name ?? 'N/A',
            'total_sent' => $totalSent,
            'total_opens' => $totalOpens,
            'total_clicks' => $totalClicks,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
        ]);
    }
}
