<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_email_operations', function (Blueprint $table) {
            $table->id();
            $table->string('email_type', 50);
            $table->enum('status', ['in_progress', 'completed', 'completed_with_failures'])->default('in_progress');
            $table->unsignedInteger('total_count');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('pending_count');
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('school_year')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('initiated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_email_operations');
    }
};
