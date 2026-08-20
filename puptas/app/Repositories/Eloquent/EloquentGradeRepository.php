<?php

namespace App\Repositories\Eloquent;

use App\Models\Grade;
use App\Repositories\Contracts\GradeRepositoryInterface;

class EloquentGradeRepository implements GradeRepositoryInterface
{
    public function existsForUser(string $userId): bool
    {
        return Grade::where('user_id', (string) $userId)->exists();
    }

    public function firstByUser(string $userId): ?Grade
    {
        return Grade::where('user_id', (string) $userId)->first();
    }
}