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
        Schema::table('grades', function (Blueprint $table) {
            // G11 Math subjects (common across strands)
            $table->decimal('g11_general_mathematics', 5, 2)->nullable()->after('science');
            $table->decimal('g11_statistics_probability', 5, 2)->nullable()->after('g11_general_mathematics');
            
            // G11 English subjects (common across strands)
            $table->decimal('g11_oral_communication', 5, 2)->nullable()->after('g11_statistics_probability');
            $table->decimal('g11_21st_century_lit', 5, 2)->nullable()->after('g11_oral_communication');
            $table->decimal('g11_academic_professional', 5, 2)->nullable()->after('g11_21st_century_lit');
            $table->decimal('g11_reading_writing', 5, 2)->nullable()->after('g11_academic_professional');
            
            // G11 Science subjects (common across strands)
            $table->decimal('g11_earth_life_science', 5, 2)->nullable()->after('g11_reading_writing');
            $table->decimal('g11_physical_science', 5, 2)->nullable()->after('g11_earth_life_science');
            
            // ABM specific
            $table->decimal('g11_business_mathematics', 5, 2)->nullable()->after('g11_physical_science');
            $table->decimal('g12_21st_century_lit', 5, 2)->nullable()->after('g11_business_mathematics');
            
            // STEM specific - G11 Math
            $table->decimal('g11_pre_calculus', 5, 2)->nullable()->after('g12_21st_century_lit');
            $table->decimal('g11_basic_calculus', 5, 2)->nullable()->after('g11_pre_calculus');
            
            // STEM specific - G11 Science
            $table->decimal('g11_earth_science', 5, 2)->nullable()->after('g11_basic_calculus');
            $table->decimal('g11_general_chemistry_1', 5, 2)->nullable()->after('g11_earth_science');
            
            // STEM specific - G12 Science
            $table->decimal('g12_general_physics_1', 5, 2)->nullable()->after('g11_general_chemistry_1');
            $table->decimal('g12_general_biology_1', 5, 2)->nullable()->after('g12_general_physics_1');
            $table->decimal('g12_general_physics_2', 5, 2)->nullable()->after('g12_general_biology_1');
            $table->decimal('g12_general_biology_2', 5, 2)->nullable()->after('g12_general_physics_2');
            $table->decimal('g12_general_chemistry_2', 5, 2)->nullable()->after('g12_general_biology_2');
            
            // STEM specific - G12 English
            $table->decimal('g12_academic_professional', 5, 2)->nullable()->after('g12_general_chemistry_2');
            
            // HUMSS specific - G12 Science
            $table->decimal('g12_earth_life_science', 5, 2)->nullable()->after('g12_academic_professional');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn([
                'g11_general_mathematics',
                'g11_statistics_probability',
                'g11_oral_communication',
                'g11_21st_century_lit',
                'g11_academic_professional',
                'g11_reading_writing',
                'g11_earth_life_science',
                'g11_physical_science',
                'g11_business_mathematics',
                'g12_21st_century_lit',
                'g11_pre_calculus',
                'g11_basic_calculus',
                'g11_earth_science',
                'g11_general_chemistry_1',
                'g12_general_physics_1',
                'g12_general_biology_1',
                'g12_general_physics_2',
                'g12_general_biology_2',
                'g12_general_chemistry_2',
                'g12_academic_professional',
                'g12_earth_life_science',
            ]);
        });
    }
};
