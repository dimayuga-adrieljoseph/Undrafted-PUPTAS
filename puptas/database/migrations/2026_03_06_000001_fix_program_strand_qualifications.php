<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Changes:
     *  - Add GAS strand to strands table (was missing)
     *  - BSIT: restrict to STEM, TVL, ICT, GAS only
     *  - BSECE: open to all strands (remove restrictions)
     *  - BSME: open to all strands (remove restrictions)
     */
    public function up(): void
    {
        // 1. Add GAS strand if it doesn't exist
        if (!DB::table('strands')->where('code', 'GAS')->exists()) {
            DB::table('strands')->insert([
                'code'       => 'GAS',
                'name'       => 'General Academic Strand',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Fix BSIT – must only accept STEM, TVL, ICT, GAS
        $bsit = DB::table('programs')->where('code', 'BSIT')->first();
        if ($bsit) {
            // Remove all existing strand links for BSIT
            DB::table('program_strand')->where('program_id', $bsit->id)->delete();

            // Re-attach the correct strands
            foreach (['STEM', 'TVL', 'ICT', 'GAS'] as $strandCode) {
                $strand = DB::table('strands')->where('code', $strandCode)->first();
                if ($strand) {
                    DB::table('program_strand')->insertOrIgnore([
                        'program_id' => $bsit->id,
                        'strand_id'  => $strand->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 3. BSECE – open to all strands (remove all restrictions)
        $bsece = DB::table('programs')->where('code', 'BSECE')->first();
        if ($bsece) {
            DB::table('program_strand')->where('program_id', $bsece->id)->delete();
        }

        // 4. BSME – open to all strands (remove all restrictions)
        $bsme = DB::table('programs')->where('code', 'BSME')->first();
        if ($bsme) {
            DB::table('program_strand')->where('program_id', $bsme->id)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore BSIT to original (STEM, TVL, ICT)
        $bsit = DB::table('programs')->where('code', 'BSIT')->first();
        if ($bsit) {
            DB::table('program_strand')->where('program_id', $bsit->id)->delete();
            foreach (['STEM', 'TVL', 'ICT'] as $strandCode) {
                $strand = DB::table('strands')->where('code', $strandCode)->first();
                if ($strand) {
                    DB::table('program_strand')->insertOrIgnore([
                        'program_id' => $bsit->id,
                        'strand_id'  => $strand->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Restore BSECE to STEM only
        $bsece = DB::table('programs')->where('code', 'BSECE')->first();
        if ($bsece) {
            $stem = DB::table('strands')->where('code', 'STEM')->first();
            if ($stem) {
                DB::table('program_strand')->insertOrIgnore([
                    'program_id' => $bsece->id,
                    'strand_id'  => $stem->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Restore BSME to STEM only
        $bsme = DB::table('programs')->where('code', 'BSME')->first();
        if ($bsme) {
            $stem = DB::table('strands')->where('code', 'STEM')->first();
            if ($stem) {
                DB::table('program_strand')->insertOrIgnore([
                    'program_id' => $bsme->id,
                    'strand_id'  => $stem->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Remove GAS strand (only if we added it)
        DB::table('strands')->where('code', 'GAS')->delete();
    }
};
