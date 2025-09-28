<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     * Crée la table 'campaigns' pour stocker les détails de chaque campagne d'emailing.
     */
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id(); // Clé primaire auto-incrémentée.
            $table->string('name'); // Nom de la campagne (ex: "Promo Été 2024").
            $table->string('subject'); // Objet de l'email (ex: "Votre offre exclusive d'été !").
            $table->string('sender_name'); // Nom de l'expéditeur (ex: "Mon Entreprise").
            $table->string('sender_email'); // Adresse email de l'expéditeur.
            $table->integer('send_frequency_minutes')->default(0); // Fréquence d'envoi en minutes (0 = aucune limite de fréquence).
            $table->integer('max_daily_sends')->default(0); // Nombre max d'envois par jour (0 = aucune limite quotidienne).
            $table->timestamp('scheduled_at')->nullable(); // Date et heure de début planifiée de la campagne.
            // Statut de la campagne : pending (en attente), active (en cours), paused (en pause), completed (terminée), failed (échouée).
            $table->enum('status', ['pending', 'active', 'paused', 'completed', 'failed'])->default('pending');
            $table->unsignedSmallInteger('progress')->default(0);

            // Clé étrangère vers la table 'templates'. Si le template est supprimé, la campagne est aussi supprimée.
            $table->foreignId('template_id')->constrained()->onDelete('cascade');
            // Clé étrangère vers la table 'smtp_servers'. Si le Serveurs API est supprimé, la campagne est aussi supprimée.
            $table->foreignId('smtp_server_id')->constrained()->onDelete('cascade');
            
            $table->timestamps(); // Ajoute automatiquement les colonnes 'created_at' et 'updated_at'.
        });
    }

    /**
     * Annule les migrations.
     * Supprime la table 'campaigns' si la migration est annulée.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
