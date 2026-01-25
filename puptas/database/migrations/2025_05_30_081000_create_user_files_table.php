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
        Schema::create('user_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('application_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('application_process_id')->nullable()->constrained('application_processes')->nullOnDelete();
            $table->string('type');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->enum('status', ['pending', 'approved', 'returned'])->default('pending');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type']);
            $table->index('status');
            $table->index('application_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_files');
    }
};
