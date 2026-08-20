<?php

namespace App\Repositories\Eloquent;

use App\Models\Application;
use App\Repositories\Contracts\ApplicationRepositoryInterface;
use Illuminate\Support\Collection;

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
        return Application::where('user_id', (string) $userId)
            ->latest('id')
            ->firstOrFail();
    }

    public function findReturnedOrRejectedByUserId(string $userId): ?Application
    {
        return Application::where('user_id', $userId)
            ->whereIn('status', ['returned', 'rejected'])
            ->first();
    }

    public function firstOrCreate(array $attributes, array $values): Application
    {
        return Application::firstOrCreate($attributes, $values);
    }

    public function userIdsWithCompletedMedical(): array
    {
        return \Illuminate\Support\Facades\DB::table('applications as a')
            ->join('application_processes as p', 'p.application_id', '=', 'a.id')
            ->whereNull('a.deleted_at')
            ->where('p.stage', 'medical')
            ->where('p.status', 'completed')
            ->whereIn('a.id', function ($q) {
                $q->selectRaw('MAX(id)')
                  ->from('applications')
                  ->whereNull('deleted_at')
                  ->groupBy('user_id');
            })
            ->pluck('a.user_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function officiallyEnrolledUserIds(): array
    {
        return \Illuminate\Support\Facades\DB::table('applications')
            ->whereNull('deleted_at')
            ->where('enrollment_status', 'officially_enrolled')
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')
                  ->from('applications')
                  ->whereNull('deleted_at')
                  ->groupBy('user_id');
            })
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function latestApplicationsByUserIds(array $userIds): Collection
    {
        if (empty($userIds)) {
            return collect();
        }

        return Application::whereIn('user_id', $userIds)
            ->whereNull('deleted_at')
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')
                  ->from('applications')
                  ->whereNull('deleted_at')
                  ->groupBy('user_id');
            })
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