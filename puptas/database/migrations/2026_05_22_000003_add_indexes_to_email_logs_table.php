<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            // Composite index for querying logs by operation and status
            $table->index(['bulk_operation_id', 'status'], 'email_logs_bulk_operation_status_index');

            // Composite index for looking up logs by recipient and type
            $table->index(['recipient_email', 'email_type'], 'email_logs_recipient_email_type_index');

            // Index on status for filtering
            $table->index('status', 'email_logs_status_index');

            // Unique constraint to prevent duplicate emails per recipient per operation
            // MySQL allows multiple NULLs in unique indexes by default
            $table->unique(['recipient_id', 'email_type', 'bulk_operation_id'], 'email_logs_recipient_type_operation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex('email_logs_bulk_operation_status_index');
            $table->dropIndex('email_logs_recipient_email_type_index');
            $table->dropIndex('email_logs_status_index');
            $table->dropUnique('email_logs_recipient_type_operation_unique');
        });
    }
};
