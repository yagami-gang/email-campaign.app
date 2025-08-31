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
        Schema::create('campaign_mailing_list', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('mailing_list_id');

            // Définition des clés étrangères
            $table->foreign('campaign_id')->references('id')->on('campaigns')->onDelete('cascade');
            $table->foreign('mailing_list_id')->references('id')->on('mailing_lists')->onDelete('cascade');

            // Ajout de la clé primaire composée pour éviter les doublons
            $table->primary(['campaign_id', 'mailing_list_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_mailing_list');
    }
};
