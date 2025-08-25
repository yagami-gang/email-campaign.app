<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     * Crée la table 'email_logs' pour enregistrer chaque tentative d'envoi d'email.
     */
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id(); // Clé primaire auto-incrémentée.
            // Clé étrangère vers la table 'campaigns'.
            // Si la campagne est supprimée, tous les logs associés à cette campagne sont aussi supprimés.
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            // Clé étrangère vers la table 'contacts'.
            // Si le contact est supprimé, tous les logs associés à ce contact sont aussi supprimés.
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            // Statut de l'envoi : pending (en attente), sent (envoyé), failed (échec), blacklisted (sur liste noire).
            $table->enum('status', ['pending', 'sent', 'failed', 'blacklisted'])->default('pending');
            $table->timestamp('sent_at')->nullable(); // Date et heure de l'envoi réel (sera null si en attente ou échec).
            $table->timestamps(); // Ajoute automatiquement les colonnes 'created_at' et 'updated_at'.
        });
    }

    /**
     * Annule les migrations.
     * Supprime la table 'email_logs' si la migration est annulée.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
