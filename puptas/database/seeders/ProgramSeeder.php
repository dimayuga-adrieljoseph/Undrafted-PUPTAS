<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Program;

class ProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        $programs = [
            ['code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology', 'strand' => 'STEM', 'math' => 85, 'science' => 85, 'english' => 85, 'gwa' => 90, 'pupcet' => 120, 'slots' => 50],
            ['code' => 'BSBA-HRM', 'name' => 'BSBA - Human Resource Management', 'strand' => 'ABM', 'math' => 80, 'science' => 80, 'english' => 80, 'gwa' => 85, 'pupcet' => 120, 'slots' => 30],
        ];

        foreach ($programs as $program) {
            Program::create($program);
        }
    }
}
