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
            // Drop redundant index that is a prefix of the new composite index to save disk space and write overhead
            $indexes = Schema::getIndexes('application_processes');
            foreach ($indexes as $index) {
                if ($index['columns'] === ['application_id', 'stage'] && !$index['unique']) {
                    $table->dropIndex($index['name']);
                }
            }

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

            // Restore original index
            $table->index(['application_id', 'stage']);
        });
    }
};
