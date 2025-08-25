<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Crée la table 'short_urls' pour stocker les URLs raccourcies utilisées pour le suivi des clics.
     */
    public function up(): void
    {
        Schema::create('short_urls', function (Blueprint $table) {
            $table->id(); // Primary key, auto-incremented.
            $table->text('original_url'); // The full original URL (can be long).
            $table->string('short_code', 10)->unique(); // Unique short code (e.g., "abc12def") for the short URL.
            $table->json('tracking_data')->nullable(); // JSON data for tracking (e.g., contact's name, email, city).
            
            // Foreign key to 'campaigns' table (optional, but links the short URL to a specific campaign).
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('cascade');
            // Foreign key to 'email_logs' table (optional, links the short URL to a specific email sent).
            $table->foreignId('email_log_id')->nullable()->constrained()->onDelete('cascade');

            $table->timestamps(); // Adds 'created_at' and 'updated_at' columns automatically.
        });
    }

    /**
     * Reverse the migrations.
     * Supprime la table 'short_urls' if the migration is rolled back.
     */
    public function down(): void
    {
        Schema::dropIfExists('short_urls');
    }
};
