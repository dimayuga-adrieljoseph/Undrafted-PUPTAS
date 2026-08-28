<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Listeners\LogUserLogin;
use App\Listeners\LogUserLogout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Events\QueryExecuted;
use Laravel\Passport\Passport;
use App\Models\Application;
use App\Models\ApplicantProfile;
use App\Models\ApplicationProcess;
use App\Observers\ApplicationObserver;
use App\Observers\ApplicantProfileObserver;
use App\Observers\ApplicationProcessObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(UrlGenerator $url): void
    {
        Auth::provider('idp', function ($app, array $config) {
            return new \App\Auth\IdpUserProvider();
        });

        if ($this->app->environment('production')) {
            $url->forceScheme('https');
        }

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

        // Custom rate limiter for the /oauth/token endpoint.
        // Passport hardcodes 'middleware' => 'throttle' which defaults to
        // 60 req/min keyed by IP. On Railway, behind the reverse proxy,
        // ALL external clients can share the same IP — causing legitimate
        // token requests to be rejected with 429.
        // We key by client_id from the POST body so each OAuth client
        // gets its own bucket.
        RateLimiter::for('oauth-token', function (Request $request) {
            $clientId = $request->input('client_id', '');
            $key = $clientId ?: $request->ip();

            return Limit::perMinute(120)->by('oauth:' . $key);
        });


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
                ->by('medical:' . ($request->bearerToken() ?: $request->ip()));
        });

        RateLimiter::for('external-medical-api-minute', function ($request) {
            return Limit::perMinute((int) config('services.external_medical_api.minute_limit', 200))
                ->by('medical:' . ($request->bearerToken() ?: $request->ip()));
        });

        RateLimiter::for('external-medical-api-daily', function ($request) {
            return Limit::perDay((int) config('services.external_medical_api.daily_limit', 1500))
                ->by('medical:' . ($request->bearerToken() ?: $request->ip()));
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

        // Override Passport's /oauth/token route AFTER Passport registers it.
        // Passport hardcodes 'middleware' => 'throttle' (60/min by IP).
        // On Railway, behind the reverse proxy, all clients can share the
        // same IP — exhausting the 60/min bucket globally.
        // We replace it with our named 'oauth-token' limiter (120/min by client_id).
        $this->app->booted(function () {
            Route::post('/oauth/token', [
                'uses' => '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken',
                'as' => 'passport.token',
                'middleware' => 'throttle:oauth-token',
            ]);
        });

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
