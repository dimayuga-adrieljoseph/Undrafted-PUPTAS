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
        Schema::table('application_processes', function (Blueprint $table) {
            $table->index(['application_id', 'stage', 'status'], 'idx_app_stage_status');
            $table->index(['stage', 'status', 'action'], 'idx_stage_status_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_processes', function (Blueprint $table) {
            $table->dropIndex('idx_app_stage_status');
            $table->dropIndex('idx_stage_status_action');
        });
    }
};
