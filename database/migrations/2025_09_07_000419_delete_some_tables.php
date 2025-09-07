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

        Schema::table('tracking_clicks', function (Blueprint $table) {
            $table->dropForeign(['email_log_id']);
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropForeign(['contact_id']);
        });

        Schema::table('tracking_opens', function (Blueprint $table) {
            $table->dropForeign(['email_log_id']);
        });

        Schema::table('campaign_contact', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropForeign(['contact_id']);
        });

        Schema::table('short_urls', function (Blueprint $table) {
            $table->dropForeign(['email_log_id']);
        });

        Schema::dropIfExists('campaign_contact');

        Schema::dropIfExists('email_logs');
        
        Schema::dropIfExists('tracking_opens');
        
        Schema::dropIfExists('contacts');

        Schema::table('tracking_clicks', function (Blueprint $table) {
            $table->dropColumn('email_log_id');
            $table->integer('id_contact');
            $table->string('id_campagne');
        });

        Schema::table('short_urls', function (Blueprint $table) {
            $table->dropColumn('email_log_id');
            $table->integer('id_contact');
            $table->integer('id_campaign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
