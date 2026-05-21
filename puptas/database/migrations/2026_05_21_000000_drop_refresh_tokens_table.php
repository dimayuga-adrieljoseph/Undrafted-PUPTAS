<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Since this drops the table to free up disk space after migrating to Redis,
        // we don't strictly need a rollback structure.
    }
};
