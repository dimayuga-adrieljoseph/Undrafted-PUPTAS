<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tracks every F137 Request Letter download by an applicant.
     * One row per applicant — updated on re-downloads.
     */
    public function up(): void
    {
        Schema::create('f137_generations', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36)->unique();
            $table->string('reference_number')->nullable();
            $table->string('filename')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('f137_generations');
    }
};
