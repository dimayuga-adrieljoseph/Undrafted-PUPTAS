<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->id();
            // user_id represents the IDP UUID. No foreign key constraints as IDP acts as the source of truth.
            $table->string('user_id', 36)->nullable();
            $table->string('school')->nullable();
            $table->string('school_address')->nullable();
            $table->string('school_year')->nullable();
            $table->date('date_graduated')->nullable();
            $table->string('strand')->nullable();
            $table->string('track')->nullable();
            $table->unsignedBigInteger('first_choice_program')->nullable();
            $table->unsignedBigInteger('second_choice_program')->nullable();
            $table->timestamps();

            $table->foreign('first_choice_program')->references('id')->on('programs')->onDelete('set null');
            $table->foreign('second_choice_program')->references('id')->on('programs')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropForeign(['first_choice_program']);
            $table->dropForeign(['second_choice_program']);
        });
        Schema::dropIfExists('applicant_profiles');
    }
};
