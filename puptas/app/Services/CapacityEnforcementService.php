<?php

namespace App\Services;

use App\Models\TestPasser;
use Illuminate\Support\Facades\DB;

class CapacityEnforcementService
{
    /**
     * Re-rank all eligible records (statuses 1, 2, 4) for a school year,
     * apply ScoreThresholdService::resolve() to the top 550 by score,
     * and demote records beyond position 550 to status 4 with null batch.
     *
     * Sorting priority:
     * 1. pupcet_total_score DESC (highest scores first)
     * 2. created_at ASC (earlier records retained as tiebreaker)
     *
     * Status 3 (Unqualified) records are excluded from capacity enforcement.
     *
     * @param string $schoolYear The school year to enforce capacity for
     * @return int Count of reassigned records
     */
    public function enforce(string $schoolYear): int
    {
        return DB::transaction(function () use ($schoolYear) {
            $scoreThresholdService = new ScoreThresholdService();

            // Query all eligible records (statuses 1, 2, 4) for the school year
            // sorted purely by score DESC with created_at ASC as tiebreaker
            $records = TestPasser::where('school_year', $schoolYear)
                ->whereIn('passer_status_id', [1, 2, 4])
                ->orderBy('pupcet_total_score', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();

            $reassignedCount = 0;

            // Apply ScoreThresholdService::resolve() to top 550 (or all if ≤ 550)
            $top = $records->take(ScoreThresholdService::CAPACITY_LIMIT);

            foreach ($top as $record) {
                $resolved = $scoreThresholdService->resolve($record->pupcet_total_score);

                if (
                    $record->passer_status_id !== $resolved['passer_status_id']
                    || $record->batch_number !== $resolved['batch_number']
                ) {
                    $record->passer_status_id = $resolved['passer_status_id'];
                    $record->batch_number = $resolved['batch_number'];
                    $record->save();
                    $reassignedCount++;
                }
            }

            // Demote records beyond position 550 to status 4 with null batch
            $beyond = $records->slice(ScoreThresholdService::CAPACITY_LIMIT);

            foreach ($beyond as $record) {
                if ($record->passer_status_id !== 4 || $record->batch_number !== null) {
                    $record->passer_status_id = 4;
                    $record->batch_number = null;
                    $record->save();
                    $reassignedCount++;
                }
            }

            return $reassignedCount;
        });
    }
}
