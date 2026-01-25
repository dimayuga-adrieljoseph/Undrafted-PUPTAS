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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['application', 'interview', 'medical', 'announcement', 'other'])->default('other');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('affected_programs')->nullable();
            $table->timestamps();

            $table->index(['type', 'start']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('schedules');
    }
};
