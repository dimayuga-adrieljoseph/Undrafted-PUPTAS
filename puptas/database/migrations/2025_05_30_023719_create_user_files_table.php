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
            $table->string('type')->nullable();          // e.g., 'file10_front', 'grade11_report'
            $table->string('file_path');                  // storage path of the file
            $table->string('original_name')->nullable();  // original uploaded file name
            $table->timestamps();
            $table->string('status')->default('pending'); // pending, approved, rejected, resubmitted
            $table->text('comment')->nullable(); 
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_files');
    }

};
