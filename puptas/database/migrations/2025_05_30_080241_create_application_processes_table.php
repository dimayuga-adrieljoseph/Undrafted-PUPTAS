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
        Schema::create('application_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->enum('stage', ['evaluator', 'interviewer', 'medical', 'records']);
            $table->enum('status', ['in_progress', 'completed', 'returned'])->default('in_progress');
            $table->enum('action', ['passed', 'returned', 'transferred', 'accepted', 'rejected'])->nullable();
            $table->text('decision_reason')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->json('files_affected')->nullable();
            $table->string('previous_stage')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['application_id', 'stage']);
            $table->index('performed_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_processes');
    }
};
