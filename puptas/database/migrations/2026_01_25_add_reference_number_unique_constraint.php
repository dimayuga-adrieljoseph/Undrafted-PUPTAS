<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('test_passers', function (Blueprint $table) {
            // Add unique constraint to reference_number to prevent duplicates
            // Important for SAR form download security verification
            $table->unique('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('test_passers', function (Blueprint $table) {
            $table->dropUnique(['reference_number']);
        });
    }
};
