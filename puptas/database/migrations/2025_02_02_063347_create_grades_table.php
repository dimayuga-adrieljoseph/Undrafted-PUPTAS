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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('english', 5, 2)->nullable();
            $table->decimal('mathematics', 5, 2)->nullable();
            $table->decimal('science', 5, 2)->nullable();
            $table->decimal('g11_first_sem', 5, 2)->nullable();
            $table->decimal('g11_second_sem', 5, 2)->nullable();
            $table->decimal('g12_first_sem', 5, 2)->nullable();
            $table->decimal('g12_second_sem', 5, 2)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
