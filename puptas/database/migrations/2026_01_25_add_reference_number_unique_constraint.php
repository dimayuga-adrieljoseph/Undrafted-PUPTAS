<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Check if table and column exist before adding unique constraint
        if (Schema::hasTable('test_passers') && Schema::hasColumn('test_passers', 'reference_number')) {
            // Check if unique index already exists
            $hasIndex = DB::select("SHOW INDEX FROM test_passers WHERE Key_name = 'test_passers_reference_number_unique'");
            
            if (empty($hasIndex)) {
                Schema::table('test_passers', function (Blueprint $table) {
                    $table->unique('reference_number');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (Schema::hasTable('test_passers') && Schema::hasColumn('test_passers', 'reference_number')) {
            Schema::table('test_passers', function (Blueprint $table) {
                $table->dropUnique(['reference_number']);
            });
        }
    }
};
