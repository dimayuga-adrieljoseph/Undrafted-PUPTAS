<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define programs with their strand associations
        // Strands are now stored in program_strand junction table (normalized)
        $programs = [
            [
                'code' => 'BSBA-HRM',
                'name' => 'Bachelor of Science in Business Administration - Human Resource Management',
                'strands' => ['ABM'],
                'math' => 82,
                'science' => 82,
                'english' => 82,
                'gwa' => 82,
                'pupcet' => 100,
                'slots' => 50,
            ],
            [
                'code' => 'BSBA-MM',
                'name' => 'Bachelor of Science in Business Administration - Marketing Management',
                'strands' => ['ABM'],
                'math' => 82,
                'science' => 82,
                'english' => 82,
                'gwa' => 82,
                'pupcet' => 100,
                'slots' => 50,
            ],
            [
                'code' => 'BSECE',
                'name' => 'Bachelor of Science in Electronics and Communications Engineering',
                'strands' => ['STEM'],
                'math' => 86,
                'science' => 86,
                'english' => 86,
                'gwa' => 88,
                'pupcet' => 100,
                'slots' => 50,
            ],
            [
                'code' => 'BSED-ENGLISH',
                'name' => 'Bachelor of Science in Education - English',
                'strands' => [], // Open to all
                'math' => 85,
                'science' => 85,
                'english' => 87,
                'gwa' => 85,
                'pupcet' => 100,
                'slots' => 50,
            ],
            [
                'code' => 'BSED-MATH',
                'name' => 'Bachelor of Science in Education - Mathematics',
                'strands' => [], // Open to all
                'math' => 87,
                'science' => 85,
                'english' => 85,
                'gwa' => 85,
                'pupcet' => 100,
                'slots' => 50,
            ],
            [
                'code' => 'BSIT',
                'name' => 'Bachelor of Science in Information Technology',
                'strands' => ['STEM', 'TVL', 'ICT'],
                'math' => 85,
                'science' => 85,
                'english' => 85,
                'gwa' => 90,
                'pupcet' => 100,
                'slots' => 50,
            ],
            [
                'code' => 'BSME',
                'name' => 'Bachelor of Science in Mechanical Engineering',
                'strands' => ['STEM'],
                'math' => 86,
                'science' => 86,
                'english' => 86,
                'gwa' => 88,
                'pupcet' => 100,
                'slots' => 50,
            ],
            [
                'code' => 'BSOA',
                'name' => 'Bachelor of Science in Office Administration',
                'strands' => ['ABM'],
                'math' => 82,
                'science' => 82,
                'english' => 82,
                'gwa' => 82,
                'pupcet' => 100,
                'slots' => 50,
            ],
            [
                'code' => 'DIT',
                'name' => 'Diploma in Information Technology',
                'strands' => [], // Open to all
                'math' => 82,
                'science' => 82,
                'english' => 82,
                'gwa' => 82,
                'pupcet' => 100,
                'slots' => 50,
            ],
            [
                'code' => 'DOMT',
                'name' => 'Diploma in Office Management and Technology',
                'strands' => ['ABM'],
                'math' => 82,
                'science' => 82,
                'english' => 82,
                'gwa' => 82,
                'pupcet' => 100,
                'slots' => 50,
            ],
            [
                'code' => 'BSPSYCH',
                'name' => 'Bachelor of Science in Psychology',
                'strands' => [], // Open to all
                'math' => 85,
                'science' => 85,
                'english' => 85,
                'gwa' => 85,
                'pupcet' => 100,
                'slots' => 50,
            ],
        ];

        // Insert programs and create strand relationships
        foreach ($programs as $programData) {
            $strands = $programData['strands'];
            unset($programData['strands']);

            $programData['created_at'] = now();
            $programData['updated_at'] = now();

            // Insert program
            DB::table('programs')->insert($programData);

            // Get the program ID
            $program = DB::table('programs')->where('code', $programData['code'])->first();

            // Create strand relationships if strands table exists and program has strand restrictions
            if ($program && !empty($strands) && DB::getSchemaBuilder()->hasTable('strands')) {
                foreach ($strands as $strandCode) {
                    $strand = DB::table('strands')->where('code', $strandCode)->first();
                    if ($strand) {
                        DB::table('program_strand')->insertOrIgnore([
                            'program_id' => $program->id,
                            'strand_id' => $strand->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }
}
