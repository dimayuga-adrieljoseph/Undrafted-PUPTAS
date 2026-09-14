<?php

namespace App\Repositories\Eloquent;

use App\Models\ApplicationProcess;
use App\Repositories\Contracts\ApplicationProcessRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentApplicationProcessRepository implements ApplicationProcessRepositoryInterface
{
    public function firstByApplicationStageStatuses(int $applicationId, string $stage, array $statuses): ?ApplicationProcess
    {
        return ApplicationProcess::where('application_id', $applicationId)
            ->where('stage', $stage)
            ->whereIn('status', $statuses)
            ->first();
    }

    public function firstOrFailByApplicationStageStatuses(int $applicationId, string $stage, array $statuses): ApplicationProcess
    {
        return ApplicationProcess::where('application_id', $applicationId)
            ->where('stage', $stage)
            ->whereIn('status', $statuses)
            ->firstOrFail();
    }

    public function firstByApplicationStage(int $applicationId, string $stage): ?ApplicationProcess
    {
        return ApplicationProcess::where('application_id', $applicationId)
            ->where('stage', $stage)
            ->first();
    }

    public function existsByApplicationStageStatus(int $applicationId, string $stage, string $status): bool
    {
        return ApplicationProcess::where('application_id', $applicationId)
            ->where('stage', $stage)
            ->where('status', $status)
            ->exists();
    }

    public function allByApplication(int $applicationId): Collection
    {
        return ApplicationProcess::where('application_id', $applicationId)
            ->orderBy('created_at')
            ->get();
    }

    public function create(array $data): ApplicationProcess
    {
        return ApplicationProcess::create($data);
    }

    public function updateOrCreate(array $attributes, array $values): ApplicationProcess
    {
        return ApplicationProcess::updateOrCreate($attributes, $values);
    }

    public function countDistinctApplications(string $stage, string $status, ?string $action = null): int
    {
        $query = \Illuminate\Support\Facades\DB::table('application_processes')
            ->where('stage', $stage)
            ->where('status', $status);

        if ($action !== null) {
            $query->where('action', $action);
        }

        return $query->distinct('application_id')->count('application_id');
    }

    public function stageInProgressSummary(array $stages): Collection
    {
        return \Illuminate\Support\Facades\DB::table('application_processes as p')
            ->join('applications as a', 'a.id', '=', 'p.application_id')
            ->whereNull('a.deleted_at')
            ->where('a.enrollment_status', '!=', 'officially_enrolled')
            ->where('p.status', 'in_progress')
            ->whereIn('p.stage', $stages)
            ->selectRaw('p.stage, COUNT(DISTINCT p.application_id) as total')
            ->groupBy('p.stage')
            ->pluck('total', 'p.stage');
    }
}