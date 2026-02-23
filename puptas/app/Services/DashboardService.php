<?php

namespace App\Services;

use App\Models\User;
use App\Models\Program;

/**
 * Dashboard Service
 * 
 * Handles business logic for dashboard data aggregation.
 * Provides common dashboard data across different role dashboards.
 */
class DashboardService
{
    protected ApplicationService $applicationService;
    protected UserService $userService;

    public function __construct(ApplicationService $applicationService, UserService $userService)
    {
        $this->applicationService = $applicationService;
        $this->userService = $userService;
    }

    /**
     * Get common dashboard data
     *
     * @return array
     */
    public function getCommonDashboardData(): array
    {
        return [
            'allUsers' => User::all(),
            'summary' => $this->applicationService->getApplicationSummary(),
        ];
    }

    /**
     * Get dashboard data with programs
     *
     * @return array
     */
    public function getDashboardDataWithPrograms(): array
    {
        $commonData = $this->getCommonDashboardData();
        
        return array_merge($commonData, [
            'programs' => Program::withCount('applications')->get(),
        ]);
    }

    /**
     * Verify user role access
     *
     * @param User $user
     * @param int $requiredRoleId
     * @return bool
     */
    public function verifyRoleAccess(User $user, int $requiredRoleId): bool
    {
        return $user->role_id === $requiredRoleId;
    }

    /**
     * Get applicants for dashboard display
     *
     * @return \Illuminate\Support\Collection
     */
    public function getApplicantsForDashboard()
    {
        return $this->userService->getApplicantsWithApplications();
    }
}
