<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extend the application_processes.action enum to include 'course_changed'.
     * MySQL ALTER TABLE … MODIFY is used for enum expansion.
     * SQLite (used in tests) does not enforce enum constraints, so no-op fallback is safe.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("
                ALTER TABLE application_processes
                MODIFY COLUMN `action`
                ENUM('passed','returned','transferred','accepted','rejected','course_changed')
                NULL
            ");
        }
        // SQLite ignores enum constraints — no ALTER needed for tests.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // Revert: first convert any 'course_changed' rows so they don't block the rollback
            DB::statement("
                UPDATE application_processes
                SET action = NULL
                WHERE action = 'course_changed'
            ");

            DB::statement("
                ALTER TABLE application_processes
                MODIFY COLUMN `action`
                ENUM('passed','returned','transferred','accepted','rejected')
                NULL
            ");
        }
    }
};
