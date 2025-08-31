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
        Schema::table('smtp_servers', function (Blueprint $table) {
            // Suppression des anciennes colonnes
            $table->dropColumn(['host', 'port', 'username', 'password', 'encryption']);
            // Ajout de la nouvelle colonne 'url'
            $table->string('url')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtp_servers', function (Blueprint $table) {
            // Re-création des colonnes pour le rollback
            $table->string('host')->after('name');
            $table->integer('port')->after('host');
            $table->string('username')->after('port');
            $table->string('password')->after('username');
            $table->string('encryption')->nullable()->after('password');
            // Suppression de la nouvelle colonne
            $table->dropColumn('url');
        });
    }
};
