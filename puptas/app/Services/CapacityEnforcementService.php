<?php

namespace App\Services;

use App\Models\TestPasser;
use Illuminate\Support\Facades\DB;

class CapacityEnforcementService
{
    /**
     * Re-rank all qualified+waitlisted records for a school year
     * and reassign status 4 (waitlisted_below_cutoff) to those beyond position 550.
     *
     * Sorting priority:
     * 1. passer_status_id ASC (qualified=1 first, then waitlisted=2)
     * 2. pupcet_total_score DESC (highest scores first)
     * 3. created_at ASC (earlier records retained as tiebreaker)
     *
     * Records beyond position 550 are updated to passer_status_id=4 with batch_number=null.
     *
     * @param string $schoolYear The school year to enforce capacity for
     * @return int Count of reassigned records
     */
    public function enforce(string $schoolYear): int
    {
        return DB::transaction(function () use ($schoolYear) {
            // Query all qualified (1) and waitlisted (2) records for the school year
            // sorted by priority: status ASC (1 before 2), score DESC, created_at ASC
            $records = TestPasser::where('school_year', $schoolYear)
                ->whereIn('passer_status_id', [1, 2])
                ->orderBy('passer_status_id', 'asc')
                ->orderBy('pupcet_total_score', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();

            // If total count is within capacity, no reassignment needed
            if ($records->count() <= ScoreThresholdService::CAPACITY_LIMIT) {
                return 0;
            }

            // Records beyond position 550 need to be reassigned
            $recordsToReassign = $records->slice(ScoreThresholdService::CAPACITY_LIMIT);

            $idsToReassign = $recordsToReassign->pluck('test_passer_id')->toArray();

            // Bulk update all records beyond the capacity limit
            $reassignedCount = TestPasser::whereIn('test_passer_id', $idsToReassign)
                ->update([
                    'passer_status_id' => 4,
                    'batch_number' => null,
                ]);

            return $reassignedCount;
        });
    }
}
