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
        if (Schema::hasTable('user_files') && Schema::hasColumn('user_files', 'application_id')) {
            Schema::table('user_files', function (Blueprint $table) {
                $table->foreignId('application_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('user_files') && Schema::hasColumn('user_files', 'application_id')) {
            Schema::table('user_files', function (Blueprint $table) {
                $table->foreignId('application_id')->nullable(false)->change();
            });
        }
    }
};
