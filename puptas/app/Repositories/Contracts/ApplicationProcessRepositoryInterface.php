<?php

namespace App\Repositories\Contracts;

use App\Models\ApplicationProcess;
use Illuminate\Support\Collection;

interface ApplicationProcessRepositoryInterface
{
    /**
     * First process matching application + stage and any of the given statuses.
     */
    public function firstByApplicationStageStatuses(int $applicationId, string $stage, array $statuses): ?ApplicationProcess;

    /**
     * First-or-fail process matching application + stage and any of the given statuses.
     */
    public function firstOrFailByApplicationStageStatuses(int $applicationId, string $stage, array $statuses): ApplicationProcess;

    /**
     * First process matching application + stage (any status).
     */
    public function firstByApplicationStage(int $applicationId, string $stage): ?ApplicationProcess;

    /**
     * Whether a process exists for application + stage + status.
     */
    public function existsByApplicationStageStatus(int $applicationId, string $stage, string $status): bool;

    /**
     * All processes for an application, oldest first.
     */
    public function allByApplication(int $applicationId): Collection;

    /**
     * Create a new application process.
     */
    public function create(array $data): ApplicationProcess;

    /**
     * Update-or-create a process by attributes.
     */
    public function updateOrCreate(array $attributes, array $values): ApplicationProcess;

    /**
     * Count distinct applications at a stage with a status (and optional action).
     */
    public function countDistinctApplications(string $stage, string $status, ?string $action = null): int;

    /**
     * Count distinct applications currently in progress per stage.
     */
    public function stageInProgressSummary(array $stages): Collection;
}