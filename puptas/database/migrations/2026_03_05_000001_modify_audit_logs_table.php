<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Replaces the old event-sourcing schema with a human-readable audit trail.
     */
    public function up(): void
    {
        $existingColumns = Schema::getColumnListing('audit_logs');

        // Drop FK and indexes using raw SQL via safeStatement
        $this->safeStatement("ALTER TABLE `audit_logs` DROP FOREIGN KEY `audit_logs_user_id_foreign`");
        $this->safeStatement("ALTER TABLE `audit_logs` DROP INDEX `audit_logs_model_type_model_id_index`");
        $this->safeStatement("ALTER TABLE `audit_logs` DROP INDEX `audit_logs_user_id_index`");
        $this->safeStatement("ALTER TABLE `audit_logs` DROP INDEX `audit_logs_created_at_index`");

        // Drop old columns
        $old = ['model_type', 'model_id', 'action', 'old_values', 'new_values', 'ip_address', 'user_agent'];
        $toDrop = array_values(array_intersect($old, $existingColumns));
        if (!empty($toDrop)) {
            Schema::table('audit_logs', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        // Add new columns
        Schema::table('audit_logs', function (Blueprint $table) use ($existingColumns) {
            if (!in_array('username', $existingColumns)) {
                $table->string('username')->nullable()->after('user_id');
            }
            if (!in_array('user_role', $existingColumns)) {
                $table->string('user_role', 50)->nullable()->after('username');
            }
            if (!in_array('log_category', $existingColumns)) {
                $table->string('log_category', 50)->nullable()->after('user_role');
            }
            if (!in_array('action_type', $existingColumns)) {
                $table->string('action_type', 50)->nullable()->after('user_role');
            }
            if (!in_array('module_name', $existingColumns)) {
                $table->string('module_name', 100)->nullable()->after('action_type');
            }
            if (!in_array('description', $existingColumns)) {
                $table->text('description')->nullable()->after('module_name');
            }
            if (!in_array('login_time', $existingColumns)) {
                $table->timestamp('login_time')->nullable()->after('description');
            }
            if (!in_array('logout_time', $existingColumns)) {
                $table->timestamp('logout_time')->nullable()->after('login_time');
            }
        });

        // Add new indexes
        $this->safeStatement("ALTER TABLE `audit_logs` ADD INDEX `audit_logs_action_type_index` (`action_type`)");
        $this->safeStatement("ALTER TABLE `audit_logs` ADD INDEX `audit_logs_user_id_index` (`user_id`)");
        $this->safeStatement("ALTER TABLE `audit_logs` ADD INDEX `audit_logs_created_at_index` (`created_at`)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $existingColumns = Schema::getColumnListing('audit_logs');

        // Drop new indexes
        $this->safeStatement("ALTER TABLE `audit_logs` DROP INDEX `audit_logs_action_type_index`");
        $this->safeStatement("ALTER TABLE `audit_logs` DROP INDEX `audit_logs_user_id_index`");
        $this->safeStatement("ALTER TABLE `audit_logs` DROP INDEX `audit_logs_created_at_index`");

        // Drop new columns
        $newCols = ['username', 'user_role', 'log_category', 'action_type', 'module_name', 'description', 'login_time', 'logout_time'];
        $toDrop = array_values(array_intersect($newCols, $existingColumns));
        if (!empty($toDrop)) {
            Schema::table('audit_logs', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        // Re-add original columns
        Schema::table('audit_logs', function (Blueprint $table) use ($existingColumns) {
            if (!in_array('model_type', $existingColumns)) {
                $table->string('model_type')->nullable();
            }
            if (!in_array('model_id', $existingColumns)) {
                $table->unsignedBigInteger('model_id')->nullable();
            }
            if (!in_array('action', $existingColumns)) {
                $table->string('action')->nullable();
            }
            if (!in_array('old_values', $existingColumns)) {
                $table->json('old_values')->nullable();
            }
            if (!in_array('new_values', $existingColumns)) {
                $table->json('new_values')->nullable();
            }
            if (!in_array('ip_address', $existingColumns)) {
                $table->string('ip_address', 45)->nullable();
            }
            if (!in_array('user_agent', $existingColumns)) {
                $table->string('user_agent', 512)->nullable();
            }
        });

        // Restore FK and original indexes
        $this->safeStatement("ALTER TABLE `audit_logs` ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
        $this->safeStatement("ALTER TABLE `audit_logs` ADD INDEX `audit_logs_model_type_model_id_index` (`model_type`, `model_id`)");
    }

    /**
     * Execute a raw SQL statement, silently ignoring errors.
     */
    private function safeStatement(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            // Silently continue
        }
    }
};
