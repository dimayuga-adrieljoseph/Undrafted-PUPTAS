<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Normalize strand data from programs table to follow 1NF
     * 
     * BEFORE (1NF Violation):
     *   programs.strand = "ABM, other with bridging"
     *   
     * AFTER (3NF - Normalized):
     *   programs table → unchanged initially (for backward compatibility)
     *   program_strand junction table:
     *     - program_id (FK to programs)
     *     - strand_id (FK to strands)
     *     - composite PK ensures one entry per program-strand pair
     */
    public function up(): void
    {
        // Create junction table for program-strand many-to-many relationship
        Schema::create('program_strand', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->foreignId('strand_id')->constrained('strands')->onDelete('cascade');
            $table->timestamps();

            // Composite unique index prevents duplicate relationships
            $table->unique(['program_id', 'strand_id']);
            $table->index('program_id');
            $table->index('strand_id');
        });

        // Migrate existing strand data from comma-separated values
        $this->migrateStrandData();
    }

    /**
     * Migrate strand data from programs.strand column to program_strand junction table
     * 
     * Examples of strand parsing:
     *   "STEM, TECH-VOC, ICT, GAS" → [STEM, TECH-VOC, ICT, GAS]
     *   "ABM, other with bridging" → [ABM]
     *   "Open to all" → [] (no strand restriction)
     */
    protected function migrateStrandData(): void
    {
        // Get all programs with strand data
        $programs = DB::table('programs')
            ->whereNotNull('strand')
            ->where('strand', '!=', '')
            ->get();

        // Valid strand codes (from strands table seeder)
        $validStrands = ['STEM', 'HUMSS', 'ABM', 'TVL', 'ICT', 'GAS', 'TECH-VOC'];

        foreach ($programs as $program) {
            $strandCodes = $this->parseStrandString($program->strand, $validStrands);

            // Insert entries for each strand associated with this program
            foreach ($strandCodes as $code) {
                $strand = DB::table('strands')->where('code', $code)->first();

                if ($strand) {
                    // Use insertOrIgnore to avoid duplicate key errors
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

    /**
     * Parse strand string into array of valid strand codes
     * 
     * Handles multiple input formats:
     *   - "STEM, TECH-VOC, ICT, GAS" (CSV list)
     *   - "ABM, other with bridging" (CSV with filtering)
     *   - "Open to all" (unrestricted - returns empty)
     */
    protected function parseStrandString(string $strandString, array $validStrands): array
    {
        $strand = trim($strandString);

        // Handle "Open to all" - no strand restriction
        if (strtolower($strand) === 'open to all') {
            return [];
        }

        // Split by comma and clean each part
        $parts = array_map('trim', explode(',', $strand));

        // Extract valid strand codes
        $codes = [];
        foreach ($parts as $part) {
            $upperPart = strtoupper($part);
            if (in_array($upperPart, $validStrands)) {
                $codes[] = $upperPart;
            }
        }

        return $codes;
    }

    public function down(): void
    {
        Schema::dropIfExists('program_strand');
    }
};
