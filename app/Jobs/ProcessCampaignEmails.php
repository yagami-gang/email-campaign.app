<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessCampaignEmails implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Le nombre de fois où le job peut être tenté.
     * @var int
     */
    public int $tries = 3;

    /**
     * Crée une instance du job.
     *
     * @param int $campaignId L'ID de la campagne à orchestrer.
     */
    public function __construct(protected int $campaignId)
    {
    }

    /**
     * Calcule un identifiant unique pour le job.
     * Empêche que plusieurs orchestrateurs pour la même campagne soient en file d'attente simultanément.
     */
    public function uniqueId(): int
    {
        return $this->campaignId;
    }

    /**
     * Exécute le job d'orchestration.
     */
    public function handle(): void
    {
        $campaign = Campaign::with('smtpServers')->find($this->campaignId);

        if (!$this->canStart($campaign)) {
            return;
        }

        // Marquer la campagne comme 'en cours' et réinitialiser la progression si nécessaire.
        if ($campaign->status !== 'active') {
            $campaign->update(['status' => 'active', 'sent_count' => 0, 'progress' => 0]);
        }

        Log::info("Orchestrateur : Lancement de la campagne #{$campaign->id} '{$campaign->name}'.");

        // Créer un job travailleur pour chaque serveur SMTP associé
        $jobs = $campaign->smtpServers->map(function ($smtpServer) {
            return new SendCampaignBatch($this->campaignId, $smtpServer->id);
        })->all();

        // Lancer les jobs dans un "lot" (batch) pour pouvoir suivre leur achèvement global.
        Bus::batch($jobs)
            ->then(function (Batch $batch) {
                // Ce bloc s'exécute seulement si TOUS les jobs du lot ont réussi.
                $campaign = Campaign::find($this->campaignId);
                // On revérifie qu'elle n'est pas déjà complétée par la mise à jour de progression
                if ($campaign && $campaign->status !== 'completed') {
                    $campaign->update(['status' => 'completed', 'progress' => 100]);
                    Log::info("Campagne #{$this->campaignId} terminée avec succès.");
                }
            })
            ->catch(function (Batch $batch, Throwable $e) {
                // Ce bloc s'exécute si UN SEUL des jobs du lot échoue définitivement.
                $campaign = Campaign::find($this->campaignId);
                if ($campaign) {
                    $campaign->update(['status' => 'failed']);
                    Log::error("Un job a échoué dans le lot de la campagne #{$this->campaignId}. La campagne est marquée comme échouée. Erreur : " . $e->getMessage());
                }
            })
            ->name("Campaign Sending - ID: {$this->campaignId}")
            ->dispatch();

        Log::info("Orchestrateur : " . count($jobs) . " jobs travailleurs ont été dispatchés pour la campagne #{$campaign->id}.");
    }

    /**
     * Vérifie si la campagne est dans un état qui permet le lancement.
     *
     * @param Campaign|null $campaign
     * @return bool
     */
    private function canStart(?Campaign $campaign): bool
    {
        if (!$campaign) {
            Log::error("Orchestrateur : Campagne ID {$this->campaignId} introuvable.");
            return false;
        }

        // On ne lance que si la campagne est explicitement planifiée, en pause, ou déjà en cours (pour la reprise par le scheduler).
        if (!in_array($campaign->status, ['scheduled', 'running', 'paused'])) {
            Log::warning("Orchestrateur : La campagne #{$campaign->id} n'est pas dans un état lançable (état actuel: {$campaign->status}).");
            return false;
        }

        if ($campaign->smtpServers->isEmpty()) {
            $campaign->update(['status' => 'failed']);
            Log::error("Orchestrateur : La campagne #{$campaign->id} a échoué car aucun serveur SMTP n'est configuré.");
            return false;
        }

        return true;
    }
}
