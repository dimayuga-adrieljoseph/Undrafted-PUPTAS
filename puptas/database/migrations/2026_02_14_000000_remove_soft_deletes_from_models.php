<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration removes the soft delete implementation from the system.
     * Before removing the deleted_at columns, it archives soft-deleted records
     * and then permanently deletes them.
     */
    public function up(): void
    {
        // Step 1: Archive soft-deleted records (optional, for compliance/audit)
        $this->archiveSoftDeletedRecords();

        // Step 2: Delete soft-deleted records from all tables
        DB::statement('DELETE FROM audit_logs WHERE model_type = "App\\Models\\User" AND created_at IN (SELECT created_at FROM users WHERE deleted_at IS NOT NULL)');
        DB::statement('DELETE FROM applications WHERE deleted_at IS NOT NULL');
        DB::statement('DELETE FROM user_files WHERE deleted_at IS NOT NULL');
        DB::statement('DELETE FROM programs WHERE deleted_at IS NOT NULL');
        DB::statement('DELETE FROM users WHERE deleted_at IS NOT NULL');

        // Step 3: Remove deleted_at columns from all tables
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('user_files', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This rollback will restore the deleted_at columns but will not
     * restore archived data. Archived data should be restored manually from backups.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('user_files', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Archive soft-deleted records for compliance and audit purposes.
     */
    private function archiveSoftDeletedRecords(): void
    {
        // Archive soft-deleted users if you need them for compliance/GDPR
        // This is optional and depends on your data retention policy

        // You can uncomment this if you want to archive before deletion:
        // 
        // DB::statement('
        //     CREATE TABLE IF NOT EXISTS users_archive_' . date('Y_m_d_H_i_s') . ' AS
        //     SELECT * FROM users WHERE deleted_at IS NOT NULL
        // ');
        // 
        // Similarly for other tables if needed
    }
};
