<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     * Crée la table 'tracking_clicks' pour enregistrer les clics sur les URLs raccourcies.
     */
    public function up(): void
    {
        Schema::create('tracking_clicks', function (Blueprint $table) {
            $table->id(); // Clé primaire auto-incrémentée.
            // Clé étrangère vers la table 'email_logs'.
            // Un clic est lié à un log d'email spécifique.
            $table->foreignId('email_log_id')->constrained()->onDelete('cascade');
            // Clé étrangère vers la table 'short_urls'.
            // Un clic est lié à une URL courte spécifique.
            $table->foreignId('short_url_id')->constrained()->onDelete('cascade');
            $table->timestamp('clicked_at')->useCurrent(); // Date et heure du clic.
            $table->timestamps(); // Ajoute automatiquement les colonnes 'created_at' et 'updated_at'.
        });
    }

    /**
     * Annule les migrations.
     * Supprime la table 'tracking_clicks' si la migration est annulée.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_clicks');
    }
};
