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
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('first_choice_program')->nullable();
            $table->unsignedBigInteger('second_choice_program')->nullable();

            $table->foreign('first_choice_program')->references('id')->on('programs')->onDelete('set null');
            $table->foreign('second_choice_program')->references('id')->on('programs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropForeign(['first_choice_program']);
            $table->dropForeign(['second_choice_program']);
            $table->dropColumn(['first_choice_program', 'second_choice_program']);
        });
    }
};
