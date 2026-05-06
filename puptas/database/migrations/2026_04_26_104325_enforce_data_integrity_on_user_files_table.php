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
        // --- 1. DDL: Create Backup Table (Auto-commits in MySQL) ---
        if (!Schema::hasTable('user_files_duplicates_backup')) {
            Schema::create('user_files_duplicates_backup', function (Blueprint $table) {
                $table->id('backup_id');
                $table->unsignedBigInteger('original_id');
                $table->string('user_id', 36)->nullable();
                $table->string('type')->nullable();
                $table->string('file_path')->nullable();
                $table->timestamp('backed_up_at')->useCurrent();
            });
        }

        $driver = DB::connection()->getDriverName();

        // --- 2. DML: Data Cleanup (Wrapped in Transaction) ---
        DB::transaction(function () use ($driver) {
            // Backup duplicates before hard-deleting
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('
                    INSERT INTO user_files_duplicates_backup (original_id, user_id, type, file_path)
                    SELECT uf.id, uf.user_id, uf.type, uf.file_path
                    FROM user_files uf
                    JOIN (
                        SELECT user_id, type, MAX(id) as keep_id
                        FROM user_files
                        GROUP BY user_id, type
                        HAVING COUNT(*) > 1
                    ) d ON uf.user_id = d.user_id AND uf.type = d.type AND uf.id <> d.keep_id
                ');

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
                // SQLite / Postgres fallback
                DB::statement('
                    INSERT INTO user_files_duplicates_backup (original_id, user_id, type, file_path)
                    SELECT uf.id, uf.user_id, uf.type, uf.file_path
                    FROM user_files uf
                    WHERE uf.id IN (
                        SELECT uf2.id
                        FROM user_files uf2
                        JOIN (
                            SELECT user_id, type, MAX(id) as keep_id
                            FROM user_files
                            GROUP BY user_id, type
                            HAVING COUNT(*) > 1
                        ) d ON uf2.user_id = d.user_id AND uf2.type = d.type AND uf2.id <> d.keep_id
                    )
                ');

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

            // Identify and clean up orphaned records (invalid application_id)
            DB::table('user_files')
                ->whereNotNull('application_id')
                ->whereNotIn('application_id', function ($query) {
                    $query->select('id')->from('applications');
                })
                ->update(['application_id' => null]);
        });

        // --- 3. DDL: Apply Schema Constraints (Auto-commits in MySQL) ---
        Schema::table('user_files', function (Blueprint $table) use ($driver) {
            // Drop existing index on ['user_id', 'type'] if it exists
            $indexes = Schema::getIndexes('user_files');
            foreach ($indexes as $index) {
                if ($index['columns'] === ['user_id', 'type'] && !$index['unique']) {
                    $table->dropIndex($index['name']);
                }
            }

            // Add UNIQUE composite index
            $table->unique(['user_id', 'type']);

            // Drop existing foreign key on application_id if it exists
            // SQLite doesn't support dropping foreign keys by name, so skip for SQLite
            if ($driver !== 'sqlite') {
                $foreignKeys = Schema::getForeignKeys('user_files');
                foreach ($foreignKeys as $fk) {
                    if ($fk['columns'] === ['application_id']) {
                        $table->dropForeign($fk['name']);
                    }
                }
            }

            // Add the new FOREIGN KEY constraint
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
        $driver = DB::connection()->getDriverName();
        
        Schema::table('user_files', function (Blueprint $table) use ($driver) {
            // Drop unique index
            $table->dropUnique(['user_id', 'type']);

            // Drop foreign key
            // SQLite doesn't support dropping foreign keys by name, so skip for SQLite
            if ($driver !== 'sqlite') {
                $foreignKeys = Schema::getForeignKeys('user_files');
                foreach ($foreignKeys as $fk) {
                    if ($fk['columns'] === ['application_id']) {
                        $table->dropForeign($fk['name']);
                    }
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
