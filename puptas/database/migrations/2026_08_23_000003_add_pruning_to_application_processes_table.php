<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an index that supports efficient pruning of old application_processes rows.
 *
 * application_processes is an append-only workflow audit trail with no retention
 * policy.  Unlike audit_logs (which has a 6-month Prunable trait), it will grow
 * indefinitely.  This migration adds an index on updated_at so the scheduled
 * pruning query (WHERE status = 'completed' AND updated_at < ?) is index-backed
 * rather than a full table scan.
 *
 * The pruning logic itself lives in ApplicationProcess::prunable() — see the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_processes', function (Blueprint $table) {
            $existing = collect(Schema::getIndexes('application_processes'))
                ->pluck('name')
                ->toArray();

            if (! in_array('idx_app_processes_pruning', $existing, true)) {
                // Composite (status, updated_at) — covers the pruning WHERE clause
                // as well as any status-only filters on the column.
                $table->index(['status', 'updated_at'], 'idx_app_processes_pruning');
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_processes', function (Blueprint $table) {
            $table->dropIndex('idx_app_processes_pruning');
        });
    }
};
