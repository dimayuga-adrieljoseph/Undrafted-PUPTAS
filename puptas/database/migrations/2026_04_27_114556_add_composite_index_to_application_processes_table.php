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
        Schema::table('application_processes', function (Blueprint $table) {
            $table->index(['application_id', 'stage', 'status', 'action'], 'idx_app_proc_composite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_processes', function (Blueprint $table) {
            $table->dropIndex('idx_app_proc_composite');
        });
    }
};
