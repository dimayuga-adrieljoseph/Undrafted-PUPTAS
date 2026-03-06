<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Open BSBA-HRM, BSBA-MM, BSOA, and DOMT to all strands
     * (remove the ABM-only restriction from these programs).
     */
    public function up(): void
    {
        $programs = ['BSBA-HRM', 'BSBA-MM', 'BSOA', 'DOMT'];

        foreach ($programs as $code) {
            $program = DB::table('programs')->where('code', $code)->first();
            if ($program) {
                DB::table('program_strand')->where('program_id', $program->id)->delete();
            }
        }
    }

    /**
     * Reverse the migrations (restore ABM-only restriction).
     */
    public function down(): void
    {
        $programs = ['BSBA-HRM', 'BSBA-MM', 'BSOA', 'DOMT'];
        $abm = DB::table('strands')->where('code', 'ABM')->first();

        if (!$abm) {
            return;
        }

        foreach ($programs as $code) {
            $program = DB::table('programs')->where('code', $code)->first();
            if ($program) {
                DB::table('program_strand')->insertOrIgnore([
                    'program_id' => $program->id,
                    'strand_id'  => $abm->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
