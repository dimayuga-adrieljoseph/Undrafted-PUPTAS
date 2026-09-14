<?php

namespace App\Repositories\Eloquent;

use App\Models\Application;
use App\Repositories\Contracts\ApplicationRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EloquentApplicationRepository implements ApplicationRepositoryInterface
{
    public function count(): int
    {
        return Application::count();
    }

    public function countByStatus(string $status): int
    {
        return Application::where('status', $status)->count();
    }

    public function countClearedForEnrollment(): int
    {
        return Application::where('status', 'cleared_for_enrollment')->count();
    }

    public function countOfficiallyEnrolled(): int
    {
        return Application::where('enrollment_status', 'officially_enrolled')->count();
    }

    public function find(int $id): Application
    {
        return Application::findOrFail($id);
    }

    public function findByUserId(string $userId): Application
    {
        $userId = (string) $userId;

        $application = Cache::remember(Application::cacheKeyForUser($userId), 3600, function () use ($userId) {
            $found = Application::latestForUser()
                ->where('user_id', $userId)
                ->first();

            return $found ?: false;
        });

        if (! $application) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('No application found for user.');
        }

        return $application;
    }

    public function findReturnedOrRejectedByUserId(string $userId): ?Application
    {
        $userId = (string) $userId;

        return Cache::remember("application:user:{$userId}:returned_rejected", 3600, function () use ($userId) {
            $found = Application::latestForUser()
                ->where('user_id', $userId)
                ->whereIn('status', ['returned', 'rejected'])
                ->first();

            return $found ?: false;
        }) ?: null;
    }

    public function firstOrCreate(array $attributes, array $values): Application
    {
        return Application::firstOrCreate($attributes, $values);
    }

    public function userIdsWithCompletedMedical(): array
    {
        // Uses Application::scopeLatestForUser() — no duplicated MAX(id) subquery.
        return Application::latestForUser()
            ->join('application_processes as p', 'p.application_id', '=', 'applications.id')
            ->where('p.stage', 'medical')
            ->where('p.status', 'completed')
            ->pluck('applications.user_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function officiallyEnrolledUserIds(): array
    {
        return Application::latestForUser()
            ->where('enrollment_status', 'officially_enrolled')
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function latestApplicationsByUserIds(array $userIds): Collection
    {
        if (empty($userIds)) {
            return collect();
        }

        return Application::latestForUser()
            ->whereIn('user_id', $userIds)
            ->with(['program:id,code,name', 'processes:id,application_id,stage,status,action,created_at'])
            ->get()
            ->keyBy('user_id');
    }

    public function passedInterviewApplications(?string $schoolYear = null): Collection
    {
        $query = Application::with(['user.testPasser', 'user'])
            ->whereHas('processes', function ($q) {
                $q->where('stage', 'interviewer')
                  ->where('status', 'completed')
                  ->whereIn('action', ['passed', 'accepted']);
            })
            ->whereHas('user');

        if ($schoolYear) {
            $query->whereHas('user.testPasser', function ($q) use ($schoolYear) {
                $q->where('school_year', $schoolYear);
            });
        }

        return $query->get();
    }

    public function chartDataRaw(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): Collection
    {
        $submitted = \Illuminate\Support\Facades\DB::table('applications')
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'),
                'status',
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as count')
            )
            ->where('status', 'submitted')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(created_at)'), 'status');

        $accepted = \Illuminate\Support\Facades\DB::table('application_processes')
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(updated_at) as date'),
                \Illuminate\Support\Facades\DB::raw("'accepted' as status"),
                \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT application_id) as count')
            )
            ->where('stage', 'interviewer')
            ->where('status', 'completed')
            ->where('action', 'passed')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(updated_at)'));

        $returned = \Illuminate\Support\Facades\DB::table('applications')
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(updated_at) as date'),
                'status',
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as count')
            )
            ->where('status', 'returned')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(updated_at)'), 'status');

        return $submitted->unionAll($accepted)->unionAll($returned)->get();
    }
}