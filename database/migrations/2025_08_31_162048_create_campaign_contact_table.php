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
        Schema::create('campaign_contact', function (Blueprint $table) {
            // Clé étrangère vers la table 'campaigns'
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');

            // Clé étrangère vers la table 'contacts'
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');

            // Définit une clé primaire composée pour assurer l'unicité de la paire.
            $table->primary(['campaign_id', 'contact_id']);

            $table->timestamps(); // Ajoute 'created_at' et 'updated_at'.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_contact');
    }
};
