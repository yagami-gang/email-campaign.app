<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     * Crée la table 'contacts' pour stocker les informations des destinataires.
     * Les champs correspondent à ceux du fichier JSON de la mailing liste.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id(); // Clé primaire auto-incrémentée.
            $table->string('email')->unique(); // Adresse email, doit être unique pour chaque contact.
            $table->string('name')->nullable(); // Nom de famille (peut être vide).
            $table->string('firstname')->nullable(); // Prénom (peut être vide).
            $table->string('cp')->nullable(); // Code postal (peut être vide).
            $table->string('department')->nullable(); // Département (peut être vide).
            $table->string('phone_number')->nullable(); // Numéro de téléphone (peut être vide).
            $table->string('city')->nullable(); // Ville (peut être vide).
            $table->string('profession')->nullable(); // Profession (peut être vide).
            $table->string('habitation')->nullable(); // Type d'habitation (peut être vide).
            $table->string('anciennete')->nullable(); // Ancienneté (peut être vide).
            $table->string('statut')->nullable(); // Statut (peut être vide).
            $table->timestamps(); // Ajoute automatiquement les colonnes 'created_at' et 'updated_at'.
        });
    }

    /**
     * Annule les migrations.
     * Supprime la table 'contacts' si la migration est annulée.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
