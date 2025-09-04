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
        Schema::create('json_files', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('file_path'); 
            $table->integer('nbre_lignes_traitees')->default(0);
            $table->integer('nbre_lignes_importees')->default(0);
            $table->enum('status', ['importation_en_cours', 'importation_terminee'])->default('importation_en_cours');
            $table->integer('campaign_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('json_files');
    }
};
