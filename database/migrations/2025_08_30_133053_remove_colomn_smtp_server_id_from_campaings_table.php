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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['smtp_server_id']);
            
            $table->dropColumn('smtp_server_id');
            $table->dropColumn('sender_name');
            $table->dropColumn('sender_email');
            $table->dropColumn('send_frequency_minutes');
            $table->dropColumn('max_daily_sends');
            $table->dropColumn('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
