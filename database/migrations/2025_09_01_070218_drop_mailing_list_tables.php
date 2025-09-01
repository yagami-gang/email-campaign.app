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
        // Supprimer la table pivot campaign_mailing_list
        Schema::table('campaign_mailing_list', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropForeign(['mailing_list_id']);
        });
        Schema::dropIfExists('campaign_mailing_list');

        // Supprimer la table pivot mailing_list_contact
        Schema::table('mailing_list_contacts', function (Blueprint $table) {
            $table->dropForeign(['mailing_list_id']);
            $table->dropForeign(['contact_id']);
        });
        Schema::dropIfExists('mailing_list_contacts');

        // Supprimer la table mailing_lists
        Schema::dropIfExists('mailing_lists');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cette méthode ne peut pas recréer les tables, car elle ne sait pas ce qu'elles contenaient.
        // Vous devrez recréer ces migrations manuellement si vous voulez les restaurer.
        // Par exemple :
        // Schema::create('mailing_lists', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->timestamps();
        // });
        // ... et ainsi de suite pour les autres tables.
    }
};
