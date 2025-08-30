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
            $table->integer('port')->nullable()->change();
            $table->string('username')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->boolean('is_active')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtp_servers', function (Blueprint $table) {
            $table->integer('port')->nullable(false)->change();
            $table->string('username')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
            $table->boolean('is_active')->default(true)->nullable(false)->change();
        });
    }
};
