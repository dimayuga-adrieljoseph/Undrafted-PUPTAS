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
        Schema::create('programs', function (Blueprint $table) {
            $table->id(); // Auto-increment ID
            $table->string('code')->unique();
            $table->string('name');
            $table->string('strand')->nullable();
            $table->integer('math')->nullable();
            $table->integer('science')->nullable();
            $table->integer('english')->nullable();
            $table->integer('gwa')->nullable();
            $table->integer('pupcet')->nullable();
            $table->integer('slots')->default(0); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
