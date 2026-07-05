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
            $table->integer('waiver_rank')->nullable();
            $table->string('waiver_list_status')->nullable();
            $table->string('waiver_program_offering')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_passers', function (Blueprint $table) {
            $table->dropColumn(['waiver_rank', 'waiver_list_status', 'waiver_program_offering']);
        });
    }
};
