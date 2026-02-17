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
        Schema::create('sar_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_passer_id')->constrained('test_passers', 'test_passer_id')->onDelete('cascade');
            $table->string('filename');
            $table->string('file_path');
            $table->string('enrollment_date');
            $table->string('enrollment_time');
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to_email')->nullable();
            $table->timestamps();
            
            $table->index('test_passer_id');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sar_generations');
    }
};
