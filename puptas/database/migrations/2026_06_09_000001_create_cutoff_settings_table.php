<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cutoff_settings', function (Blueprint $table) {
            $table->id();
            // Stores the cutoff deadline in UTC; application code converts to/from Asia/Manila
            $table->timestampTz('cutoff_at')->nullable();
            $table->timestamps();
        });

        // Seed one empty row so reads always find a record (no null-row ambiguity).
        // CutoffSettingsService always does an UPDATE on this singleton row (ID = 1).
        DB::table('cutoff_settings')->insert(['cutoff_at' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cutoff_settings');
    }
};
