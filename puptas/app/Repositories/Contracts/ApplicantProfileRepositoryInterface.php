<?php

namespace App\Repositories\Contracts;

use App\Models\ApplicantProfile;
use Illuminate\Support\Collection;

interface ApplicantProfileRepositoryInterface
{
    /**
     * All applicants with their current application (and program/processes).
     */
    public function allWithCurrentApplication(): Collection;

    /**
     * Applicants pending for a stage, optionally scoped to program IDs.
     */
    public function byStage(string $stage, ?array $programIds = null): Collection;

    /**
     * All applicants who reached a stage (any status), optionally scoped to program IDs.
     */
    public function allByStage(string $stage, ?array $programIds = null): Collection;

    /**
     * Applicant profiles for the given user IDs.
     */
    public function byUserIds(array $userIds, array $columns = ['*']): Collection;

    /**
     * Total number of applicant profiles.
     */
    public function count(): int;

    /**
     * Applicant profiles with first choice program / current / enrolled application.
     */
    public function applicantsWithDetails(): Collection;

    /**
     * Applicant profiles matching the search term (name or email).
     */
    public function search(?string $term): Collection;

    /**
     * Number of applicant profiles matching the search term.
     */
    public function countSearch(?string $term): int;
}