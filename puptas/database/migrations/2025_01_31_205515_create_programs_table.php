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
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('strand', 100)->nullable();
            $table->decimal('math', 5, 2)->nullable();
            $table->decimal('science', 5, 2)->nullable();
            $table->decimal('english', 5, 2)->nullable();
            $table->decimal('gwa', 5, 2)->nullable();
            $table->decimal('pupcet', 6, 2)->nullable();
            $table->integer('slots')->default(0);
            $table->timestamps();
            $table->softDeletes();
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
