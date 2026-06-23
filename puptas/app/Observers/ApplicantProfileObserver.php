<?php

namespace App\Observers;

use App\Models\ApplicantProfile;
use Illuminate\Support\Facades\Cache;

class ApplicantProfileObserver
{
    /**
     * Handle the ApplicantProfile "created" event.
     */
    public function created(ApplicantProfile $applicantProfile): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the ApplicantProfile "updated" event.
     */
    public function updated(ApplicantProfile $applicantProfile): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the ApplicantProfile "deleted" event.
     */
    public function deleted(ApplicantProfile $applicantProfile): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the ApplicantProfile "restored" event.
     */
    public function restored(ApplicantProfile $applicantProfile): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the ApplicantProfile "force deleted" event.
     */
    public function forceDeleted(ApplicantProfile $applicantProfile): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Clear the dashboard and applications cache tags.
     */
    protected function clearDashboardCache(): void
    {
        Cache::tags(['dashboard', 'applications'])->flush();
    }
}
