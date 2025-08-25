<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     * Crée la table pivot 'campaign_smtp_server' pour lier les campagnes aux serveurs SMTP.
     */
    public function up(): void
    {
        Schema::create('campaign_smtp_server', function (Blueprint $table) {
            // Clé étrangère vers la table 'campaigns'
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            // Clé étrangère vers la table 'smtp_servers'
            $table->foreignId('smtp_server_id')->constrained()->onDelete('cascade');
            
            // Définit une clé primaire composée pour assurer l'unicité de la paire
            $table->primary(['campaign_id', 'smtp_server_id']);
            $table->timestamps(); // created_at et updated_at
        });
    }

    /**
     * Annule les migrations.
     * Supprime la table 'campaign_smtp_server'.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_smtp_server');
    }
};
