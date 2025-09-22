<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Renommer la table principale "smtp_servers" en "api_endpoints"
        Schema::rename('smtp_servers', 'api_endpoints');

        // 2. Renommer la table pivot "campaign_smtp_server" en "campaign_api_endpoint"
        // Note : On utilise le nouveau nom 'campaign_api_endpoint' pour la modification suivante
        Schema::rename('campaign_smtp_server', 'campaign_api_endpoint');

        // 3. Renommer le champ dans la nouvelle table pivot
        Schema::table('campaign_api_endpoint', function (Blueprint $table) {
            // La méthode renameColumn prend l'ancien nom et le nouveau nom
            $table->renameColumn('smtp_server_id', 'api_endpoint_id');
        });
    }

    /**
     * Annule les migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // On effectue les opérations dans l'ordre inverse pour annuler

        // 3. Renommer le champ dans la table pivot pour revenir à l'original
        Schema::table('campaign_api_endpoint', function (Blueprint $table) {
            $table->renameColumn('api_endpoint_id', 'smtp_server_id');
        });

        // 2. Renommer la table pivot pour revenir à l'original
        Schema::rename('campaign_api_endpoint', 'campaign_smtp_server');

        // 1. Renommer la table principale pour revenir à l'original
        Schema::rename('api_endpoints', 'smtp_servers');
    }
};
