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
        // First, add the new composite indexes (the new idx_app_stage_status index
        // covers application_id as its leftmost column, satisfying the FK constraint)
        Schema::table('application_processes', function (Blueprint $table) {
            $table->index(['application_id', 'stage', 'status'], 'idx_app_stage_status');
            $table->index(['stage', 'status', 'action'], 'idx_stage_status_action');
        });

        // Now safe to drop the old index — MySQL can use idx_app_stage_status for the FK
        Schema::table('application_processes', function (Blueprint $table) {
            $indexes = Schema::getIndexes('application_processes');
            foreach ($indexes as $index) {
                if ($index['columns'] === ['application_id', 'stage'] && !$index['unique']) {
                    $table->dropIndex($index['name']);
                }
            }
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

            // Restore original index
            $table->index(['application_id', 'stage']);
        });
    }
};
