<?php

namespace App\Repositories\Eloquent;

use App\Models\TestPasser;
use App\Repositories\Contracts\TestPasserRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EloquentTestPasserRepository implements TestPasserRepositoryInterface
{
    public function firstByUser(string $userId): ?TestPasser
    {
        $userId = (string) $userId;

        return Cache::remember(TestPasser::cacheKeyForUser($userId), 3600, function () use ($userId) {
            $testPasser = TestPasser::where('user_id', $userId)->first();

            return $testPasser ?: false;
        }) ?: null;
    }

    public function eligibleForCapacity(string $schoolYear): Collection
    {
        return TestPasser::where('school_year', $schoolYear)
            ->whereIn('passer_status_id', [1, 2, 4])
            ->orderBy('pupcet_total_score', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function onProbation(?string $schoolYear = null): Collection
    {
        $query = TestPasser::where('passer_status_id', 5);

        if ($schoolYear) {
            $query->where('school_year', $schoolYear);
        }

        return $query->orderBy('surname')->get();
    }
}