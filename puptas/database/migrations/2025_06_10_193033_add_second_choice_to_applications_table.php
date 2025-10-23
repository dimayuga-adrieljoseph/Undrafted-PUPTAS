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
    Schema::table('applications', function (Blueprint $table) {
        $table->foreignId('second_choice_id')->nullable()->constrained('programs')->nullOnDelete();
    });
}

public function down()
{
    Schema::table('applications', function (Blueprint $table) {
        $table->dropForeign(['second_choice_id']);
        $table->dropColumn('second_choice_id');
    });
}

};
