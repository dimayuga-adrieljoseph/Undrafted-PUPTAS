<?php

namespace App\Observers;

use App\Models\Application;
use Illuminate\Support\Facades\Cache;

class ApplicationObserver
{
    /**
     * Handle the Application "created" event.
     */
    public function created(Application $application): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the Application "updated" event.
     */
    public function updated(Application $application): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the Application "deleted" event.
     */
    public function deleted(Application $application): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the Application "restored" event.
     */
    public function restored(Application $application): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the Application "force deleted" event.
     */
    public function forceDeleted(Application $application): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Clear the dashboard and applications cache tags.
     */
    protected function clearDashboardCache(): void
    {
        try {
            Cache::tags(['dashboard', 'applications'])->flush();
        } catch (\BadMethodCallException $e) {
            Cache::forget('applicants_with_applications');
            Cache::forget('application_summary');
        }
    }
}
