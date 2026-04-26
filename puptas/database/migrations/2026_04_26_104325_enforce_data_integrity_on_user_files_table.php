<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Resolve existing duplicate (user_id, type) rows
        // Keep the record with the maximum ID for each duplicate group, delete the rest.
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('
                DELETE uf FROM user_files uf
                JOIN (
                    SELECT user_id, type, MAX(id) as keep_id
                    FROM user_files
                    GROUP BY user_id, type
                    HAVING COUNT(*) > 1
                ) d ON uf.user_id = d.user_id AND uf.type = d.type AND uf.id <> d.keep_id
            ');
        } else {
            // For SQLite (testing) or PostgreSQL, use standard IN with subquery
            DB::statement('
                DELETE FROM user_files
                WHERE id IN (
                    SELECT uf.id
                    FROM user_files uf
                    JOIN (
                        SELECT user_id, type, MAX(id) as keep_id
                        FROM user_files
                        GROUP BY user_id, type
                        HAVING COUNT(*) > 1
                    ) d ON uf.user_id = d.user_id AND uf.type = d.type AND uf.id <> d.keep_id
                )
            ');
        }

        // 2. Identify and clean up orphaned records (invalid application_id)
        // Set application_id to null if the referenced application does not exist.
        DB::table('user_files')
            ->whereNotNull('application_id')
            ->whereNotIn('application_id', function ($query) {
                $query->select('id')->from('applications');
            })
            ->update(['application_id' => null]);

        // 3. Apply schema changes
        Schema::table('user_files', function (Blueprint $table) {
            // Drop existing index on ['user_id', 'type'] if it exists to avoid duplication before making it UNIQUE
            $indexes = Schema::getIndexes('user_files');
            foreach ($indexes as $index) {
                if ($index['columns'] === ['user_id', 'type'] && !$index['unique']) {
                    $table->dropIndex($index['name']);
                }
            }

            // Add UNIQUE composite index
            $table->unique(['user_id', 'type']);

            // Drop existing foreign key on application_id if it exists
            $foreignKeys = Schema::getForeignKeys('user_files');
            foreach ($foreignKeys as $fk) {
                if ($fk['columns'] === ['application_id']) {
                    $table->dropForeign($fk['name']);
                }
            }

            // Add the new FOREIGN KEY constraint with ON DELETE SET NULL
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_files', function (Blueprint $table) {
            // Drop unique index
            $table->dropUnique(['user_id', 'type']);

            // Drop foreign key
            $foreignKeys = Schema::getForeignKeys('user_files');
            foreach ($foreignKeys as $fk) {
                if ($fk['columns'] === ['application_id']) {
                    $table->dropForeign($fk['name']);
                }
            }

            // Restore original simple index
            $table->index(['user_id', 'type']);

            // Restore original foreign key (ON DELETE CASCADE)
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onDelete('cascade');
        });
    }
};
