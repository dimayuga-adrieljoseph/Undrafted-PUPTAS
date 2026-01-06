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
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('school')->nullable();
            $table->string('school_address')->nullable();
            $table->string('school_year')->nullable();
            $table->date('date_graduated')->nullable();
            $table->string('strand')->nullable();
            $table->string('track')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_profiles');
    }
};
