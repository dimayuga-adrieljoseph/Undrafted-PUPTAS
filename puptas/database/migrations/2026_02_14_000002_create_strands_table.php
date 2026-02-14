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
        // Create strands lookup table
        Schema::create('strands', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();  // STEM, HUMSS, ABM, TVL, ICT
            $table->string('name', 100);            // Full descriptive name
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
        });

        // Seed initial strand data
        DB::table('strands')->insert([
            ['code' => 'STEM', 'name' => 'Science, Technology, Engineering & Mathematics', 'is_active' => true],
            ['code' => 'HUMSS', 'name' => 'Humanities & Social Sciences', 'is_active' => true],
            ['code' => 'ABM', 'name' => 'Accountancy, Business & Management', 'is_active' => true],
            ['code' => 'TVL', 'name' => 'Technical-Vocational-Livelihood', 'is_active' => true],
            ['code' => 'ICT', 'name' => 'Information and Communications Technology', 'is_active' => true],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strands');
    }
};
