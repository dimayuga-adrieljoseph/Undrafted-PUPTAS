<?php

namespace App\Services;

use App\Models\TestPasser;
use App\Repositories\Contracts\TestPasserRepositoryInterface;
use App\Services\ScoreThresholdService;
use Illuminate\Support\Facades\DB;

class CapacityEnforcementService
{
    public function __construct(
        protected TestPasserRepositoryInterface $testPasserRepository,
        protected ScoreThresholdService $scoreThresholdService,
    ) {}

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
            // Query all eligible records (statuses 1, 2, 4) for the school year
            // sorted purely by score DESC with created_at ASC as tiebreaker
            $records = $this->testPasserRepository->eligibleForCapacity($schoolYear);

            // ── Build grouped update buckets instead of N individual saves ────────
            //
            // For the top 550, group IDs by the (passer_status_id, batch_number)
            // pair that ScoreThresholdService assigns them.  Then issue one UPDATE
            // per group instead of one UPDATE per record — worst case 3 queries
            // instead of up to 550.
            //
            // For records beyond position 550 collect all IDs that actually need
            // to change and issue a single batch UPDATE.
            //
            // IMPORTANT: DB::table()->update() bypasses Eloquent model events, so
            // the TestPasser::saved hook that busts the per-user cache will not fire.
            // We collect all affected user_ids and bust their cache entries manually
            // after the updates, before the transaction commits.

            $top    = $records->take(ScoreThresholdService::CAPACITY_LIMIT);
            $beyond = $records->slice(ScoreThresholdService::CAPACITY_LIMIT);

            // --- top 550: group by resolved (status, batch) ---
            // [ "{status}:{batch}" => ['ids' => [...], 'status' => x, 'batch' => y] ]
            $topGroups    = [];
            $affectedUsers = []; // user_ids whose cache must be busted

            foreach ($top as $record) {
                $resolved = $this->scoreThresholdService->resolve($record->pupcet_total_score);

                // Skip if already correct — no write needed
                if (
                    $record->passer_status_id === $resolved['passer_status_id']
                    && $record->batch_number  === $resolved['batch_number']
                ) {
                    continue;
                }

                $key = $resolved['passer_status_id'] . ':' . ($resolved['batch_number'] ?? 'null');

                if (! isset($topGroups[$key])) {
                    $topGroups[$key] = [
                        'passer_status_id' => $resolved['passer_status_id'],
                        'batch_number'     => $resolved['batch_number'],
                        'ids'              => [],
                    ];
                }

                $topGroups[$key]['ids'][]   = $record->id;
                $affectedUsers[]            = (string) $record->user_id;
            }

            $reassignedCount = 0;

            foreach ($topGroups as $group) {
                $reassignedCount += DB::table('test_passers')
                    ->whereIn('test_passer_id', $group['ids'])
                    ->update([
                        'passer_status_id' => $group['passer_status_id'],
                        'batch_number'     => $group['batch_number'],
                        'updated_at'       => now(),
                    ]);
            }

            // --- beyond 550: single batch demotion ---
            $beyondNeedingUpdate = $beyond->filter(
                fn ($r) => $r->passer_status_id !== 4 || $r->batch_number !== null
            );

            $demoteIds = $beyondNeedingUpdate->pluck('id')->all();

            if (! empty($demoteIds)) {
                $reassignedCount += DB::table('test_passers')
                    ->whereIn('test_passer_id', $demoteIds)
                    ->update([
                        'passer_status_id' => 4,
                        'batch_number'     => null,
                        'updated_at'       => now(),
                    ]);

                foreach ($beyondNeedingUpdate as $r) {
                    $affectedUsers[] = (string) $r->user_id;
                }
            }

            // ── Bust per-user caches for all changed records ──────────────────
            // This replicates what TestPasser::booted()'s saved hook would have
            // done if we had used individual $record->save() calls.
            foreach (array_unique($affectedUsers) as $userId) {
                \Illuminate\Support\Facades\Cache::forget(
                    \App\Models\TestPasser::cacheKeyForUser($userId)
                );
            }

            return $reassignedCount;
        });
    }
}
