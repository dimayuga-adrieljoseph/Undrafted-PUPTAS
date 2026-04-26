<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the `student_number_sequences` table and adds a UNIQUE constraint
     * on `applicant_profiles.student_number` as a safety-net against duplicates.
     */
    public function up(): void
    {
        // ── 1. Sequences table ────────────────────────────────────────────────
        Schema::create('student_number_sequences', function (Blueprint $table) {
            $table->id();
            // Composite unique key: one row per year + prefix (e.g. 2026 + MED)
            $table->unsignedSmallInteger('year');
            $table->string('prefix', 20);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['year', 'prefix']);
        });

        // ── 2. Seed the sequence table from existing data ─────────────────────
        //
        // Scan existing student numbers to populate the sequences table so that
        // the next generated number never collides with data already in the DB.
        // Pattern expected:  YYYY-PREFIX-NNNN
        if (Schema::hasColumn('applicant_profiles', 'student_number')) {
            $rows = DB::table('applicant_profiles')
                ->whereNotNull('student_number')
                ->select('student_number')
                ->get();

            $maxByKey = [];
            foreach ($rows as $row) {
                $parts = explode('-', $row->student_number);
                // We expect at least 3 segments: year, prefix, number
                if (count($parts) >= 3) {
                    $year   = $parts[0];
                    // Everything in the middle is the prefix (handles multi-segment prefixes)
                    $num    = array_pop($parts);
                    array_shift($parts);
                    $prefix = implode('-', $parts);

                    if (is_numeric($year) && is_numeric($num)) {
                        $key = "{$year}-{$prefix}";
                        $maxByKey[$key] = max($maxByKey[$key] ?? 0, (int) $num);
                    }
                }
            }

            foreach ($maxByKey as $key => $lastNum) {
                [$year, $prefix] = explode('-', $key, 2);
                DB::table('student_number_sequences')->insert([
                    'year'        => (int) $year,
                    'prefix'      => $prefix,
                    'last_number' => $lastNum,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        // ── 3. Add UNIQUE constraint on applicant_profiles.student_number ─────
        //
        // Only add if the constraint does not already exist (safe for re-runs).
        if (Schema::hasColumn('applicant_profiles', 'student_number')) {
            // Detect and log any existing duplicates before adding the constraint.
            $duplicates = DB::table('applicant_profiles')
                ->select('student_number', DB::raw('COUNT(*) as cnt'))
                ->whereNotNull('student_number')
                ->groupBy('student_number')
                ->having('cnt', '>', 1)
                ->get();

            if ($duplicates->isNotEmpty()) {
                $list = $duplicates->map(fn ($d) => "{$d->student_number} (×{$d->cnt})")->implode(', ');
                throw new \RuntimeException(
                    "Cannot add UNIQUE constraint on applicant_profiles.student_number: " .
                    "duplicate values detected: {$list}. " .
                    "Run php artisan db:seed --class=DeduplicateStudentNumbersSeeder to resolve them first."
                );
            }

            Schema::table('applicant_profiles', function (Blueprint $table) {
                $table->unique('student_number', 'applicant_profiles_student_number_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('applicant_profiles', 'student_number')) {
            Schema::table('applicant_profiles', function (Blueprint $table) {
                $table->dropUnique('applicant_profiles_student_number_unique');
            });
        }

        Schema::dropIfExists('student_number_sequences');
    }
};
