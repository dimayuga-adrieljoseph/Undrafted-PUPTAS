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
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        
        // All tables that have a user_id column that needs changing to a string
        $allTablesToModify = [
            'applications',
            'grades',
            'test_passers',
            'user_files',
            'program_user',
            'applicant_profiles',
            'audit_logs',
            'sessions',
            'teams',
            'team_user'
        ];

        // SQLite doesn't support dropping foreign keys by name, so skip for SQLite
        if ($driver !== 'sqlite') {
            foreach ($allTablesToModify as $tableName) {
                if (Schema::hasTable($tableName)) {
                    // First safely drop the foreign key if it exists
                    $foreignKeys = Schema::getForeignKeys($tableName);
                    $fkNameToDrop = null;

                    foreach ($foreignKeys as $fk) {
                        if (in_array('user_id', $fk['columns'])) {
                            $fkNameToDrop = $fk['name'];
                            break;
                        }
                    }

                    if ($fkNameToDrop) {
                        Schema::table($tableName, function (Blueprint $table) use ($fkNameToDrop) {
                            $table->dropForeign($fkNameToDrop);
                        });
                    }
                }
            }
        }

        foreach ($allTablesToModify as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    // Re-declare as string(36) to support UUIDs natively, preserving nullability
                    $table->string('user_id', 36)->nullable()->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // To reverse, we convert strings back to unsigned big integers and restore keys.
        // NOTE: This will likely cause data truncation if UUIDs are present in the table.

        $allTablesToModify = [
            'applications',
            'grades',
            'test_passers',
            'user_files',
            'program_user',
            'applicant_profiles',
            'audit_logs',
            'sessions',
            'teams',
            'team_user'
        ];

        foreach ($allTablesToModify as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    // Change back to integer. NOTE: Data might be lost if non-integer.
                    $table->unsignedBigInteger('user_id')->nullable()->change();
                });
            }
        }

        $tablesWithForeignKeys = [
            'applications',
            'grades',
            'test_passers',
            'user_files',
            'program_user',
            'applicant_profiles',
            'audit_logs'
        ];

        // Ensure users table exists before restoring constraints
        if (Schema::hasTable('users')) {
            foreach ($tablesWithForeignKeys as $tableName) {
                if (Schema::hasTable($tableName)) {
                    Schema::table($tableName, function (Blueprint $table) {
                        try {
                            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                        } catch (\Exception $e) {
                            \Log::warning("Could not restore foreign key for {$tableName}: " . $e->getMessage());
                        }
                    });
                }
            }
        }
    }
};
