<?php

namespace App\Repositories\Eloquent;

use App\Models\Grade;
use App\Repositories\Contracts\GradeRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class EloquentGradeRepository implements GradeRepositoryInterface
{
    public function existsForUser(string $userId): bool
    {
        return (bool) $this->firstByUser($userId);
    }

    public function firstByUser(string $userId): ?Grade
    {
        $userId = (string) $userId;

        return Cache::remember(Grade::cacheKeyForUser($userId), 3600, function () use ($userId) {
            $grade = Grade::where('user_id', $userId)->first();

            // Cache misses as false so repeated lookups for missing records
            // don't hit the database every request.
            return $grade ?: false;
        }) ?: null;
    }
}