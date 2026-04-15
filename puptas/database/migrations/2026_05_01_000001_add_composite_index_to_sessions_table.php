<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a composite index on sessions(user_id, last_activity).
 *
 * The default sessions table only has individual indexes on user_id and
 * last_activity. When the session garbage collector runs a DELETE query
 * such as:
 *
 *   DELETE FROM sessions WHERE last_activity <= ? AND user_id IS NOT NULL
 *
 * MySQL must do a full index scan on one column and then filter on the
 * other. On a busy system this can cause table-level locking that delays
 * session writes, which in turn makes active sessions appear expired.
 *
 * A composite index lets the database satisfy both predicates in a single
 * index range scan, dramatically reducing lock contention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            // Only add if it doesn't already exist (safe to re-run)
            $table->index(['user_id', 'last_activity'], 'sessions_user_id_last_activity_index');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex('sessions_user_id_last_activity_index');
        });
    }
};
