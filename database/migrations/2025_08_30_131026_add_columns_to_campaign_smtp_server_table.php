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
        Schema::table('campaign_smtp_server', function (Blueprint $table) {
            $table->string('sender_name'); // nom de l'expéditeur.
            $table->string('sender_email'); // Adresse email de l'expéditeur.
            $table->integer('send_frequency_minutes')->default(0); // Fréquence d'envoi en minutes (0 = aucune limite de fréquence).
            $table->integer('max_daily_sends')->default(0); // Nombre max d'envois par jour (0 = aucune limite quotidienne).
            $table->timestamp('scheduled_at')->nullable(); // Date et heure de début planifiée de la campagne.
            // Statut de la campagne : pending (en attente), active (en cours), paused (en pause), completed (terminée), failed (échouée).
            $table->enum('status', ['pending', 'active', 'paused', 'completed', 'failed'])->default('pending');
            $table->integer('progress')->default(0);
            $table->integer('nbre_contacts')->default(0);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->integer('nbre_contacts')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       
    }
};
