<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Listeners\LogUserLogin;
use App\Listeners\LogUserLogout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\Passport;
use App\Models\Application;
use App\Models\ApplicantProfile;
use App\Models\ApplicationProcess;
use App\Observers\ApplicationObserver;
use App\Observers\ApplicantProfileObserver;
use App\Observers\ApplicationProcessObserver;

use App\Repositories\Contracts\ApplicationRepositoryInterface;
use App\Repositories\Contracts\ApplicationProcessRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\ApplicantProfileRepositoryInterface;
use App\Repositories\Contracts\ProgramRepositoryInterface;
use App\Repositories\Contracts\GradeRepositoryInterface;
use App\Repositories\Contracts\UserFileRepositoryInterface;
use App\Repositories\Contracts\TestPasserRepositoryInterface;
use App\Repositories\Contracts\CutoffSettingsRepositoryInterface;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\EmailLogRepositoryInterface;
use App\Repositories\Contracts\BulkEmailOperationRepositoryInterface;

use App\Repositories\Eloquent\EloquentApplicationRepository;
use App\Repositories\Eloquent\EloquentApplicationProcessRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Repositories\Eloquent\EloquentApplicantProfileRepository;
use App\Repositories\Eloquent\EloquentProgramRepository;
use App\Repositories\Eloquent\EloquentGradeRepository;
use App\Repositories\Eloquent\EloquentUserFileRepository;
use App\Repositories\Eloquent\EloquentTestPasserRepository;
use App\Repositories\Eloquent\EloquentCutoffSettingsRepository;
use App\Repositories\Eloquent\EloquentSystemSettingRepository;
use App\Repositories\Eloquent\EloquentAuditLogRepository;
use App\Repositories\Eloquent\EloquentEmailLogRepository;
use App\Repositories\Eloquent\EloquentBulkEmailOperationRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApplicationRepositoryInterface::class, EloquentApplicationRepository::class);
        $this->app->bind(ApplicationProcessRepositoryInterface::class, EloquentApplicationProcessRepository::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(ApplicantProfileRepositoryInterface::class, EloquentApplicantProfileRepository::class);
        $this->app->bind(ProgramRepositoryInterface::class, EloquentProgramRepository::class);
        $this->app->bind(GradeRepositoryInterface::class, EloquentGradeRepository::class);
        $this->app->bind(UserFileRepositoryInterface::class, EloquentUserFileRepository::class);
        $this->app->bind(TestPasserRepositoryInterface::class, EloquentTestPasserRepository::class);
        $this->app->bind(CutoffSettingsRepositoryInterface::class, EloquentCutoffSettingsRepository::class);
        $this->app->bind(SystemSettingRepositoryInterface::class, EloquentSystemSettingRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, EloquentAuditLogRepository::class);
        $this->app->bind(EmailLogRepositoryInterface::class, EloquentEmailLogRepository::class);
        $this->app->bind(BulkEmailOperationRepositoryInterface::class, EloquentBulkEmailOperationRepository::class);
    }

    public function boot(UrlGenerator $url): void
    {
        Auth::provider('idp', function ($app, array $config) {
            return new \App\Auth\IdpUserProvider();
        });

        if ($this->app->environment('production')) {
            $url->forceScheme('https');
        }

        // Strictly prevent N+1 lazy loading in non-production environments so
        // accidental lazy loads surface as exceptions during development/testing.
        Model::preventLazyLoading(! $this->app->isProduction());

        Application::observe(ApplicationObserver::class);
        ApplicantProfile::observe(ApplicantProfileObserver::class);
        ApplicationProcess::observe(ApplicationProcessObserver::class);

        Event::listen(Login::class,  LogUserLogin::class);
        Event::listen(Logout::class, LogUserLogout::class);

        Passport::tokensCan([
            'medical-read' => 'Fetch applicant medical profiles',
            'medical-write' => 'Submit medical webhook results',
            'student-read' => 'Fetch enrolled student profiles',
            'program-read' => 'Fetch active programs list',
        ]);

        RateLimiter::for('external-api-second', function ($request) {
            return Limit::perSecond((int) config('services.external_api.second_limit', 5))
                ->by((string) $request->ip());
        });

        RateLimiter::for('external-api-minute', function ($request) {
            return Limit::perMinute((int) config('services.external_api.minute_limit', 1000))
                ->by((string) $request->ip());
        });

        RateLimiter::for('external-api-daily', function ($request) {
            return Limit::perDay((int) config('services.external_api.daily_limit', 2000))
                ->by((string) $request->ip());
        });

        RateLimiter::for('external-program-api-daily', function ($request) {
            return Limit::perDay((int) config('services.external_program_api.daily_limit', 50))
                ->by((string) $request->ip());
        });

        RateLimiter::for('external-medical-api-second', function ($request) {
            return Limit::perSecond((int) config('services.external_medical_api.second_limit', 10))
                ->by('medical:' . ($request->user()?->getKey() ?? $request->ip()));
        });

        RateLimiter::for('external-medical-api-minute', function ($request) {
            return Limit::perMinute((int) config('services.external_medical_api.minute_limit', 200))
                ->by('medical:' . ($request->user()?->getKey() ?? $request->ip()));
        });

        RateLimiter::for('external-medical-api-daily', function ($request) {
            return Limit::perDay((int) config('services.external_medical_api.daily_limit', 1500))
                ->by('medical:' . ($request->user()?->getKey() ?? $request->ip()));
        });

        RateLimiter::for('grade-extraction', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id);
        });

        // Public: status check — three stacked limits for abuse/security protection:
        //
        //   1. Per reference number, per minute (5/min)
        //      Stops enumeration: a real student never needs more than 2 checks.
        //      Even across distributed IPs, each reference number has its own counter.
        //
        //   2. Per reference number, per day (20/day)
        //      Hard daily ceiling per record. Prevents slow, patient enumeration
        //      that stays under the per-minute limit by spacing out requests.
        //
        //   3. Per IP, per minute (30/min)
        //      Backstop against flooding with random/garbage reference numbers.
        //      30/min is enough for ~30 students on the same school WiFi checking
        //      simultaneously, while still limiting a single attacker meaningfully.
        RateLimiter::for('status-checker', function (Request $request) {
             $refNumber = (string) $request->input('referenceNumber', '');
            $refKey    = 'ref:' . hash('sha256', $refNumber);
            $ipKey     = 'ip:' . $request->ip();

            return [
                // Layer 1: 10 checks/min per reference number
                Limit::perMinute(10)
                    ->by($refKey),

                // Layer 2: 60 checks/day per reference number (slow enumeration prevention)
                Limit::perDay(60)
                    ->by($refKey . ':daily'),

                // Layer 3: 60 requests/min per IP (flood backstop, safe for shared WiFi)
                Limit::perMinute(60)
                    ->by($ipKey),
            ];
        });

        RateLimiter::for('emails', function () {
            return Limit::perSecond(2);
        });

        Passport::setClientUuids(true);

        // Warn if the cache driver does not support tags or atomic locks.
        // Both are used in this application (Cache::lock() for stampede prevention,
        // Cache::tags() in some places) and silently degrade with file/array drivers.
        // This surfaces misconfigurations early in the application boot log.
        $cacheDriver = config('cache.default', 'file');
        if (in_array($cacheDriver, ['array', 'file'], true)) {
            Log::warning('Cache driver does not support tags or atomic locks.', [
                'driver'  => $cacheDriver,
                'impact'  => 'Cache::lock() stampede protection and tag-based invalidation are unavailable. Set CACHE_STORE=redis in production.',
            ]);
        }

        DB::listen(function (QueryExecuted $query) {
            if ($query->time > 500) {
                Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'time_ms' => $query->time,
                ]);
            }
        });
    }
}
