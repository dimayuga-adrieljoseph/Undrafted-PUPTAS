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
        $tablesToModify = [
            'schedules' => 'created_by',
            'sar_generations' => 'created_by_user_id',
            'application_processes' => 'performed_by'
        ];

        // Ensure we safely drop the foreign keys first
        foreach ($tablesToModify as $tableName => $columnName) {
            if (Schema::hasTable($tableName)) {
                $foreignKeys = Schema::getForeignKeys($tableName);
                $fkNameToDrop = null;

                foreach ($foreignKeys as $fk) {
                    if (in_array($columnName, $fk['columns'])) {
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

        // Now gracefully modify the column types to match the IDP
        foreach ($tablesToModify as $tableName => $columnName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($columnName) {
                    $table->string($columnName, 36)->nullable()->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting this migration is not recommended since `users` is retired
        // and string UUIDs cannot be cleanly converted back to unsignedBigInteger.
    }
};
