<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('program_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('second_choice_id')->nullable()->constrained('programs')->onDelete('set null');
            $table->enum('status', ['draft', 'submitted', 'returned', 'accepted', 'rejected', 'waitlist'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->enum('enrollment_status', ['pending', 'enrolled', 'rejected', 'waitlist'])->default('pending');
            $table->integer('enrollment_position')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('status');
            $table->index('program_id');
            $table->index('created_at');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
