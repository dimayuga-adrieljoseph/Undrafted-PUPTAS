<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CheckIdpHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'idp:check-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the health of the IDP and caches the status for the emergency login fallback.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // If IDP Health check is disabled (e.g., in staging), we do not run it
        // and we ensure the cache is set to false (meaning IDP is NOT down).
        if (!config('services.idp.health_check_enabled', true)) {
            Cache::put('idp_status_down', false);
            $this->info('IDP Health check is disabled in this environment.');
            return Command::SUCCESS;
        }

        $idpBaseUrl = config('services.idp.base_url');
        if (empty($idpBaseUrl)) {
            $this->error('IDP Base URL is not configured.');
            return Command::FAILURE;
        }

        $healthEndpoint = rtrim($idpBaseUrl, '/') . '/.well-known/openid-configuration';

        try {
            $response = Http::timeout(5)->get($healthEndpoint);

            if ($response->successful()) {
                Cache::put('idp_status_down', false);
                $this->info('IDP is UP.');
            } else {
                Cache::put('idp_status_down', true);
                $this->error('IDP is DOWN. HTTP Status: ' . $response->status());
            }
        } catch (\Exception $e) {
            Cache::put('idp_status_down', true);
            $this->error('IDP is DOWN. Exception: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
