<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Consolidated: includes audit fields (created_by_user_id, email_sent_successfully)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sar_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_passer_id')->constrained('test_passers', 'test_passer_id')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->string('file_path');
            $table->string('enrollment_date');
            $table->string('enrollment_time');
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to_email')->nullable();
            $table->boolean('email_sent_successfully')->default(false);
            $table->timestamps();

            $table->index('test_passer_id');
            $table->index('created_by_user_id');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sar_generations');
    }
};
