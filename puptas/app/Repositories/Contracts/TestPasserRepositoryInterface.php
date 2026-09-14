<?php

namespace App\Repositories\Contracts;

use App\Models\TestPasser;
use Illuminate\Support\Collection;

interface TestPasserRepositoryInterface
{
    /**
     * First test passer for a user ID.
     */
    public function firstByUser(string $userId): ?TestPasser;

    /**
     * Eligible records (statuses 1, 2, 4) for a school year, score desc then created asc.
     */
    public function eligibleForCapacity(string $schoolYear): Collection;

    /**
     * On-probation (status 5) records, optionally filtered by school year.
     */
    public function onProbation(?string $schoolYear = null): Collection;
}