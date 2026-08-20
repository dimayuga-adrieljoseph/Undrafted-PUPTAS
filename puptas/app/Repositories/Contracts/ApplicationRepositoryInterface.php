<?php

namespace App\Repositories\Contracts;

use App\Models\Application;
use Illuminate\Support\Collection;

interface ApplicationRepositoryInterface
{
    /**
     * Total number of applications (including trashed).
     */
    public function count(): int;

    /**
     * Number of applications with the given status.
     */
    public function countByStatus(string $status): int;

    /**
     * Number of applications cleared for enrollment.
     */
    public function countClearedForEnrollment(): int;

    /**
     * Number of officially enrolled applications.
     */
    public function countOfficiallyEnrolled(): int;

    /**
     * Find an application by ID or fail.
     */
    public function find(int $id): Application;

    /**
     * Find the latest application for a user ID or fail.
     */
    public function findByUserId(string $userId): Application;

    /**
     * Find the returned/rejected application for a user.
     */
    public function findReturnedOrRejectedByUserId(string $userId): ?Application;

    /**
     * First-or-create an application for the given attributes.
     */
    public function firstOrCreate(array $attributes, array $values): Application;

    /**
     * User IDs whose latest application has completed medical stage.
     *
     * @return array<int, string>
     */
    public function userIdsWithCompletedMedical(): array;

    /**
     * User IDs whose latest application is officially enrolled.
     *
     * @return array<int, string>
     */
    public function officiallyEnrolledUserIds(): array;

    /**
     * Latest (max id) applications for the given user IDs, eager loaded.
     */
    public function latestApplicationsByUserIds(array $userIds): Collection;

    /**
     * Raw chart rows (submitted / accepted / returned) between the two dates.
     */
    public function chartDataRaw(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): Collection;

    /**
     * Applications that passed the interviewer stage (for SIS upload),
     * optionally filtered by linked test passer school year.
     */
    public function passedInterviewApplications(?string $schoolYear = null): Collection;
}