<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('passer_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('status')->unique(); // will store 'qualified' or 'waitlisted'
            $table->timestamps();
        });

        // Insert initial statuses
        DB::table('passer_statuses')->insert([
            ['status' => 'qualified', 'created_at' => now(), 'updated_at' => now()],
            ['status' => 'waitlisted', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passer_statuses');
    }
};