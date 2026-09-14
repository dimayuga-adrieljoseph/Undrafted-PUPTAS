<?php

namespace App\Repositories\Contracts;

use App\Models\Grade;

interface GradeRepositoryInterface
{
    /**
     * Whether any grades exist for the given user ID.
     */
    public function existsForUser(string $userId): bool;

    /**
     * First grade record for a user ID.
     */
    public function firstByUser(string $userId): ?Grade;
}