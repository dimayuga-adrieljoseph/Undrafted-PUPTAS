<?php

namespace App\Observers;

use App\Models\ApplicationProcess;
use Illuminate\Support\Facades\Cache;

class ApplicationProcessObserver
{
    /**
     * Handle the ApplicationProcess "created" event.
     */
    public function created(ApplicationProcess $applicationProcess): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the ApplicationProcess "updated" event.
     */
    public function updated(ApplicationProcess $applicationProcess): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the ApplicationProcess "deleted" event.
     */
    public function deleted(ApplicationProcess $applicationProcess): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the ApplicationProcess "restored" event.
     */
    public function restored(ApplicationProcess $applicationProcess): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the ApplicationProcess "force deleted" event.
     */
    public function forceDeleted(ApplicationProcess $applicationProcess): void
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
