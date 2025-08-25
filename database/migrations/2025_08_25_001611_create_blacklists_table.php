<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     * Crée la table 'blacklist' pour stocker les emails des utilisateurs désinscrits.
     */
    public function up(): void
    {
        Schema::create('blacklist', function (Blueprint $table) {
            $table->id(); // Clé primaire auto-incrémentée.
            $table->string('email')->unique(); // L'email désinscrit (doit être unique pour éviter les doublons).
            $table->timestamp('blacklisted_at')->useCurrent(); // Date et heure du blacklistage.
            // Clé étrangère optionnelle vers le template utilisé lors du blacklistage (pour le suivi).
            // Si le template est supprimé, cette colonne sera mise à NULL.
            $table->foreignId('template_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps(); // Ajoute automatiquement les colonnes 'created_at' et 'updated_at'.
        });
    }

    /**
     * Annule les migrations.
     * Supprime la table 'blacklist' si la migration est annulée.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklist');
    }
};
