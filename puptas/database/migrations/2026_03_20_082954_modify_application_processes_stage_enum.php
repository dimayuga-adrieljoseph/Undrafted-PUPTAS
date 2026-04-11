<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            try {
                Schema::table('application_processes', function (Blueprint $table) {
                    $table->dropForeign(['performed_by']);
                });
            } catch (\Exception $e) {
                // Ignore error if foreign key doesn't exist or SQLite dropping is unsupported
            }
        } else {
            Schema::table('application_processes', function (Blueprint $table) {
                $foreignKeys = DB::select(
                    "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'application_processes' AND COLUMN_NAME = 'performed_by' AND REFERENCED_TABLE_NAME IS NOT NULL"
                );

                if (!empty($foreignKeys)) {
                    $table->dropForeign(['performed_by']);
                }
            });
        }
        
        Schema::table('application_processes', function (Blueprint $table) {
            $table->string('performed_by')->nullable()->change();
        });
    }

    public function down(): void
    {
    }
};
