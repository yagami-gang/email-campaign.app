<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MailingListController;
use App\Http\Controllers\TemplateController;

Route::get('/', function () {
    return view('welcome');
});
// Les routes publiques (non authentifiées)
Route::get('/unsubscribe/{encryptedEmail}', [BlacklistController::class, 'unsubscribeForm'])->name('unsubscribe.form');
Route::post('/unsubscribe', [BlacklistController::class, 'unsubscribe'])->name('unsubscribe.process');
// Routes pour les services de tracking (publiques)
Route::get('/track/open/{emailLogId}', [TrackingController::class, 'open'])->name('track.open');
Route::get('/l/{shortCode}', [TrackingController::class, 'click'])->name('track.click');

// Les routes de l'admin devraient être protégées par un middleware d'authentification et un préfixe
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Route pour l'import
    Route::post('/admin/mailing-lists/import', [MailingListController::class, 'import'])->name('admin.mailing_lists.import');

    // Nouvelle route pour récupérer la progression
    Route::get('/admin/mailing-lists/{id}/progress', [MailingListController::class, 'getImportProgress'])->name('admin.mailing_lists.progress');

    // Routes de ressources pour les templates (CRUD)
    Route::resource('templates', TemplateController::class);

    // Routes de ressources pour les serveurs SMTP (CRUD)
    Route::resource('smtp_servers', SmtpServerController::class);

    // Routes de ressources pour les campagnes (CRUD)
    Route::resource('campaigns', CampaignController::class);

     // Routes spécifiques pour les actions de campagne (lancer, pause, reprendre)
    Route::post('campaigns/{campaign}/launch', [CampaignController::class, 'launch'])->name('campaigns.launch');
    Route::post('campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume');
    
    // Nouvelle route pour récupérer la progression d'envoi d'une campagne
    Route::get('campaigns/{id}/progress', [CampaignController::class, 'getSendProgress'])->name('campaigns.progress');

    // Route pour la page principale des statistiques
    Route::get('statistics', [StatisticController::class, 'index'])->name('statistics.index');
    // Route API pour récupérer les statistiques d'une campagne spécifique
    Route::get('statistics/{campaignId}', [StatisticController::class, 'show'])->name('statistics.show');
});