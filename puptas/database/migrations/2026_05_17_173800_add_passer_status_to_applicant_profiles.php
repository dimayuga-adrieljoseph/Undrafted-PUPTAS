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
        Schema::table('test_passers', function (Blueprint $table) {
            $table->foreignId('passer_status_id')->nullable()->constrained('passer_statuses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_passers', function (Blueprint $table) {
            $table->dropForeign(['passer_status_id']);
            $table->dropColumn('passer_status_id');
        });
    }
};