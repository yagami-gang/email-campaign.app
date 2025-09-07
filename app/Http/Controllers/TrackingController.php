<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\ShortUrl;
use App\Models\TrackingOpen;
use App\Models\TrackingClick;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response; // Pour les réponses HTTP

class TrackingController extends Controller
{
    /**
     * Gère l'ouverture d'un email via un pixel de suivi.
     * Cette méthode sert une image 1x1 transparente et enregistre l'ouverture.
     *
     * @param int $emailLogId L'ID du log d'email associé.
     * @return \Illuminate\Http\Response
     */
    public function open(int $emailLogId)
    {
        // Récupère le log d'email correspondant
        $emailLog = EmailLog::find($emailLogId);

        if ($emailLog) {
            // Vérifie si l'ouverture n'a pas déjà été enregistrée pour ce log d'email (pour éviter les doublons)
            if (!$emailLog->trackingOpen) {
                // Crée un nouvel enregistrement d'ouverture
                TrackingOpen::create([
                    'email_log_id' => $emailLogId,
                    'opened_at' => now(),
                ]);
                // Optionnel: Mettre à jour le statut du EmailLog pour indiquer qu'il a été ouvert
                // $emailLog->update(['status' => 'opened']);
                Log::info("Ouverture d'email enregistrée pour EmailLog ID: {$emailLogId}");
            } else {
                Log::info("Ouverture d'email déjà enregistrée pour EmailLog ID: {$emailLogId}.");
            }
        } else {
            Log::warning("Tentative d'enregistrement d'ouverture pour EmailLog ID invalide: {$emailLogId}");
        }

        // Retourne une image 1x1 pixel transparente
        $pixel = base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
        return Response::make($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($pixel),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Gère la redirection d'une URL courte et enregistre le clic.
     *
     * @param string $shortCode Le code court de l'URL.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function click(string $shortCode)
    {
        // Récupère l'URL courte correspondante
        $shortUrl = ShortUrl::where('short_code', $shortCode)->first();

        if ($shortUrl) {
            // Enregistre le clic
            TrackingClick::create([
                'id_contact' => $shortUrl->id_contact,
                'id_campaign' => $shortUrl->id_campaign,
                'short_url_id' => $shortUrl->id,
                'clicked_at' => now(),
            ]);
            Log::info("Clic enregistré pour l'URL courte {$shortCode}, redirigeant vers: {$shortUrl->original_url}");

            // Redirige l'utilisateur vers l'URL originale
            return redirect()->away($shortUrl->original_url);
        }

        Log::warning("Tentative d'accès à une URL courte invalide: {$shortCode}");
        // Si l'URL courte n'est pas trouvée, redirige vers la page d'accueil ou une page 404
        return redirect('/'); // ou abort(404);
    }
}
