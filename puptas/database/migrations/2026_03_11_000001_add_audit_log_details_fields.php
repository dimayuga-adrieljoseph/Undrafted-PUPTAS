<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds additional fields needed for comprehensive audit logging.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Add missing fields for detailed audit logging
            if (!Schema::hasColumn('audit_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('audit_logs', 'user_agent')) {
                $table->string('user_agent', 512)->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('audit_logs', 'request_url')) {
                $table->string('request_url', 512)->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('audit_logs', 'session_id')) {
                $table->string('session_id', 100)->nullable()->after('request_url');
            }
            if (!Schema::hasColumn('audit_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('session_id');
            }
            if (!Schema::hasColumn('audit_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }
        });

        // Add indexes for commonly queried fields
        $this->addIndexSafe('audit_logs', 'ip_address');
        $this->addIndexSafe('audit_logs', 'action_type');
        $this->addIndexSafe('audit_logs', 'module_name');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn([
                'ip_address',
                'user_agent',
                'request_url',
                'session_id',
                'old_values',
                'new_values',
            ]);
        });
    }

    /**
     * Safely add an index, ignoring errors if it already exists.
     */
    private function addIndexSafe(string $table, string $column): void
    {
        try {
            \Illuminate\Support\Facades\DB::statement(
                "ALTER TABLE `{$table}` ADD INDEX `{$table}_{$column}_index` (`{$column}`)"
            );
        } catch (\Throwable $e) {
            // Index may already exist, ignore
        }
    }
};
