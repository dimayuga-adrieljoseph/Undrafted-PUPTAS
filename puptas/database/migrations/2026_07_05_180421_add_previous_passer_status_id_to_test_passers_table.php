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
            $table->unsignedBigInteger('previous_passer_status_id')->nullable()->after('passer_status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_passers', function (Blueprint $table) {
            $table->dropColumn('previous_passer_status_id');
        });
    }
};
