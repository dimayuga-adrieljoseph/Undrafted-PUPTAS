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
        Schema::create('test_passers', function (Blueprint $table) {
            $table->id('test_passer_id');
            $table->string('surname');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('address')->nullable();
            $table->string('school_address')->nullable();
            $table->string('shs_school')->nullable();
            $table->string('strand')->nullable();
            $table->year('year_graduated')->nullable();
            $table->string('email')->unique();
            $table->string('reference_number')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('school_year')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('test_passers');
    }
};
