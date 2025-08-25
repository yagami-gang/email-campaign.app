<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     * Crée la table pivot 'mailing_list_contacts' pour lier les contacts aux mailing lists.
     * Un contact peut être dans plusieurs listes, et une liste contient plusieurs contacts.
     */
    public function up(): void
    {
        Schema::create('mailing_list_contacts', function (Blueprint $table) {
            // Clé étrangère vers la table 'mailing_lists'
           
            $table->foreignId('mailing_list_id')->constrained()->onDelete('cascade');

            // Clé étrangère vers la table 'contacts'
         
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');

            // Définit une clé primaire composée pour assurer l'unicité de la paire (une liste + un contact = une seule entrée).
            $table->primary(['mailing_list_id', 'contact_id']);

            $table->timestamps(); // Ajoute automatiquement les colonnes 'created_at' et 'updated_at'.
        });
    }

    /**
     * Annule les migrations.
     * Supprime la table 'mailing_list_contacts' si la migration est annulée.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailing_list_contacts');
    }
};
