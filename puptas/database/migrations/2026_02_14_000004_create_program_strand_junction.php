<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_strand', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->foreignId('strand_id')->constrained('strands')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['program_id', 'strand_id']);
            $table->index('program_id');
            $table->index('strand_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_strand');
    }
};
