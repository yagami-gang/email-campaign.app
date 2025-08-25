<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     * Crée la table 'tracking_opens' pour enregistrer les ouvertures d'emails.
     */
    public function up(): void
    {
        Schema::create('tracking_opens', function (Blueprint $table) {
            $table->id(); // Clé primaire auto-incrémentée.
            // Clé étrangère vers la table 'email_logs'.
            // Un enregistrement d'ouverture est lié à un log d'email spécifique.
            // Si le log d'email est supprimé, l'enregistrement d'ouverture associé est aussi supprimé.
            $table->foreignId('email_log_id')->constrained()->onDelete('cascade');
            $table->timestamp('opened_at')->useCurrent(); // Date et heure de l'ouverture de l'email.
            $table->timestamps(); // Ajoute automatiquement les colonnes 'created_at' et 'updated_at'.
        });
    }

    /**
     * Annule les migrations.
     * Supprime la table 'tracking_opens' si la migration est annulée.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_opens');
    }
};
