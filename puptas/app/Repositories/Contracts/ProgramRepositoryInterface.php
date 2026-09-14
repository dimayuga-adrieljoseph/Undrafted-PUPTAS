<?php

namespace App\Repositories\Contracts;

use App\Models\Program;
use Illuminate\Support\Collection;

interface ProgramRepositoryInterface
{
    /**
     * All programs with their strands.
     */
    public function allWithStrands(): Collection;

    /**
     * All programs.
     */
    public function all(): Collection;

    /**
     * Create a program.
     */
    public function create(array $data): Program;

    /**
     * Find a program by ID or fail.
     */
    public function find(int $id): Program;

    /**
     * All programs with applications count.
     */
    public function allWithApplicationsCount(): Collection;

    /**
     * All programs with slots greater than zero.
     */
    public function allWithAvailableSlots(): Collection;

    /**
     * All program IDs.
     *
     * @return array<int, int>
     */
    public function allIds(): array;

    /**
     * Programs with strands and grade-threshold scalar filters.
     */
    public function eligibleForGrades(float $english, float $math, float $science, float $gwa): Collection;

    /**
     * Programs with strands, selected columns only.
     */
    public function allWithStrandsSelected(array $columns): Collection;
}