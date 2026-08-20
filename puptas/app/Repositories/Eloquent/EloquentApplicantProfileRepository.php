<?php

namespace App\Repositories\Eloquent;

use App\Models\ApplicantProfile;
use App\Repositories\Contracts\ApplicantProfileRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentApplicantProfileRepository implements ApplicantProfileRepositoryInterface
{
    public function allWithCurrentApplication(): Collection
    {
        return ApplicantProfile::select(['user_id', 'firstname', 'lastname', 'email'])
            ->with(['currentApplication.program', 'currentApplication.processes:id,application_id,stage,status,action,created_at'])
            ->whereHas('currentApplication')
            ->get();
    }

    public function byStage(string $stage, ?array $programIds = null): Collection
    {
        return ApplicantProfile::select(['user_id', 'firstname', 'lastname', 'email'])
            ->with(['currentApplication' => function ($query) {
                $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id');
            }, 'currentApplication.program' => function ($query) {
                $query->select('id', 'code', 'name');
            }, 'currentApplication.processes' => function ($query) use ($stage) {
                $query->where('stage', $stage)
                    ->orderBy('created_at', 'desc')
                    ->select('id', 'application_id', 'stage', 'status', 'action', 'created_at');
            }])
            ->whereHas('currentApplication', function ($query) use ($stage, $programIds) {
                $query->whereNotIn('status', ['accepted', 'cleared_for_enrollment'])
                    ->whereHas('processes', function ($q) use ($stage) {
                        $q->where('stage', $stage)
                            ->where('status', 'in_progress');
                    })
                    ->whereDoesntHave('processes', function ($q) use ($stage) {
                        $q->where('stage', $stage)
                            ->where('status', 'completed')
                            ->whereIn('action', ['passed', 'transferred']);
                    });

                if (!empty($programIds)) {
                    $query->whereIn('program_id', $programIds);
                }
            })
            ->get();
    }

    public function allByStage(string $stage, ?array $programIds = null): Collection
    {
        return ApplicantProfile::select(['user_id', 'firstname', 'lastname', 'email'])
            ->with(['currentApplication' => function ($query) {
                $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.enrollment_status', 'applications.created_at', 'applications.program_id', 'applications.second_choice_id', 'applications.third_choice_id', 'applications.requires_guidance_office', 'applications.requires_admission_office');
            }, 'currentApplication.program' => function ($query) {
                $query->select('id', 'code', 'name', 'slots');
            }, 'currentApplication.secondChoice' => function ($query) {
                $query->select('id', 'code', 'name', 'slots');
            }, 'currentApplication.thirdChoice' => function ($query) {
                $query->select('id', 'code', 'name', 'slots');
            }, 'currentApplication.processes' => function ($query) {
                $query->orderBy('created_at', 'desc')
                    ->select('id', 'application_id', 'stage', 'status', 'action', 'created_at');
            }])
            ->whereHas('currentApplication', function ($query) use ($stage, $programIds) {
                $query->whereHas('processes', function ($q) use ($stage) {
                    $q->where('stage', $stage)
                        ->whereIn('status', ['in_progress', 'completed']);
                });

                if (!empty($programIds)) {
                    $query->whereIn('program_id', $programIds);
                }
            })
            ->get();
    }

    public function byUserIds(array $userIds, array $columns = ['*']): Collection
    {
        if (empty($userIds)) {
            return collect();
        }

        return ApplicantProfile::whereIn('user_id', $userIds)->get($columns);
    }

    public function count(): int
    {
        return ApplicantProfile::count();
    }

    public function applicantsWithDetails(): Collection
    {
        return ApplicantProfile::with([
            'firstChoiceProgram:id,name,code',
            'currentApplication' => function ($query) {
                $query->select('applications.id', 'applications.user_id', 'applications.program_id', 'applications.enrollment_status');
            },
            'currentApplication.program:id,name,code',
            'officiallyEnrolledApplication' => function ($query) {
                $query->select('applications.id', 'applications.user_id', 'applications.program_id', 'applications.enrollment_status');
            },
            'officiallyEnrolledApplication.program:id,name,code',
        ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function search(?string $term): Collection
    {
        $query = ApplicantProfile::with([
            'firstChoiceProgram:id,name,code',
            'currentApplication' => function ($q) {
                $q->select('applications.id', 'applications.user_id', 'applications.program_id', 'applications.enrollment_status');
            },
            'currentApplication.program:id,name,code',
            'officiallyEnrolledApplication' => function ($q) {
                $q->select('applications.id', 'applications.user_id', 'applications.program_id', 'applications.enrollment_status');
            },
            'officiallyEnrolledApplication.program:id,name,code',
        ]);

        $this->applyNameEmailTerm($query, $term);

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function countSearch(?string $term): int
    {
        $query = ApplicantProfile::query();
        $this->applyNameEmailTerm($query, $term);

        return $query->count();
    }

    private function applyNameEmailTerm($query, ?string $term): void
    {
        if (!$term) {
            return;
        }

        $like = '%' . $term . '%';
        $query->where(function ($q) use ($like) {
            $q->where('firstname', 'like', $like)
              ->orWhere('lastname', 'like', $like)
              ->orWhere('email', 'like', $like);
        });
    }
}