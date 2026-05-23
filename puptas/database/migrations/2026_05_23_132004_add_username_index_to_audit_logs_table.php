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
        Schema::table('audit_logs', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('audit_logs'))->pluck('name')->toArray();
            if (!in_array('audit_logs_username_index', $existingIndexes)) {
                $table->index('username', 'audit_logs_username_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('audit_logs'))->pluck('name')->toArray();
            if (in_array('audit_logs_username_index', $existingIndexes)) {
                $table->dropIndex('audit_logs_username_index');
            }
        });
    }
};
