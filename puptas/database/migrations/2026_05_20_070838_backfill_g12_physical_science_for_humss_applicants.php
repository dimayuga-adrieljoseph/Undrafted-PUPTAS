<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill g12_physical_science for HUMSS applicants whose grade was lost.
 *
 * Root cause:
 *   The column g12_physical_science did not exist when HUMSS applicants
 *   submitted their grades. The controller tried to save it but the column
 *   was missing, so the value was silently dropped. The `science` average
 *   (used for qualification) was saved correctly because it was computed
 *   on the frontend before submission — only the individual breakdown field
 *   was lost.
 *
 * What this migration does:
 *   For every HUMSS applicant who has a grades row where g12_physical_science
 *   IS NULL but the `science` average IS set, we back-fill g12_physical_science
 *   with the stored `science` average as the best available approximation.
 *
 *   For HUMSS the science average = (g11_earth_life_science + g12_physical_science) / 2
 *   so the true g12_physical_science = (2 * science) - g11_earth_life_science.
 *   We use that formula when g11_earth_life_science is available, otherwise
 *   we fall back to the science average itself.
 *
 * This is a best-effort recovery. The actual grade can only be confirmed by
 * the applicant re-submitting, but since grades are locked after submission
 * this at least restores a reasonable value for display and record purposes.
 *
 * Safe to re-run — only touches rows where g12_physical_science IS NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Find all HUMSS applicants with a grades row that is missing g12_physical_science
        $affected = DB::table('grades')
            ->join('applicant_profiles', 'applicant_profiles.user_id', '=', DB::raw('CAST(grades.user_id AS CHAR)'))
            ->where('applicant_profiles.strand', 'HUMSS')
            ->whereNull('grades.g12_physical_science')
            ->whereNotNull('grades.science')
            ->select(
                'grades.id as grade_id',
                'grades.science',
                'grades.g11_earth_life_science',
                'applicant_profiles.user_id',
                'applicant_profiles.firstname',
                'applicant_profiles.lastname'
            )
            ->get();

        $count = 0;

        foreach ($affected as $row) {
            // Best-effort formula:
            // science_avg = (g11_earth_life_science + g12_physical_science) / 2
            // → g12_physical_science = (2 * science_avg) - g11_earth_life_science
            if ($row->g11_earth_life_science !== null) {
                $recovered = (2 * $row->science) - $row->g11_earth_life_science;
                // Clamp to valid grade range
                $recovered = max(0, min(100, round($recovered, 2)));
            } else {
                // No individual G11 science grade available — use the average as-is
                $recovered = round($row->science, 2);
            }

            DB::table('grades')
                ->where('id', $row->grade_id)
                ->update(['g12_physical_science' => $recovered]);

            Log::info('backfill_g12_physical_science', [
                'user_id'              => $row->user_id,
                'name'                 => "{$row->firstname} {$row->lastname}",
                'science_avg'          => $row->science,
                'g11_earth_life'       => $row->g11_earth_life_science,
                'recovered_g12_phys'   => $recovered,
            ]);

            $count++;
        }

        Log::info("backfill_g12_physical_science: updated {$count} HUMSS grade records.");
    }

    public function down(): void
    {
        // Intentionally a no-op.
        // We cannot safely determine which rows were backfilled vs originally set,
        // so we do not null them out on rollback.
    }
};
