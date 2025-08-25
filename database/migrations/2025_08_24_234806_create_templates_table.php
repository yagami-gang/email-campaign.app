<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     * Crée la table 'templates' pour stocker les modèles d'emails HTML.
     */
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id(); // Clé primaire auto-incrémentée.
            $table->string('name')->unique(); // Nom unique du template (ex: "Newsletter Promotion", "Email Bienvenue").
            $table->longText('html_content'); // Contenu HTML complet du template.
            $table->boolean('is_active')->default(true); // Indique si le template peut être utilisé ou non (par défaut, oui).
            $table->timestamps(); // Ajoute automatiquement les colonnes 'created_at' et 'updated_at'.
        });
    }

    /**
     * Annule les migrations.
     * Supprime la table 'templates' si la migration est annulée.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
