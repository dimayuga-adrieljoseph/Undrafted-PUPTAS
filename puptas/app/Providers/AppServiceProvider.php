<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Listeners\LogUserLogin;
use App\Listeners\LogUserLogout;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;

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
            return Limit::perSecond((int) config('services.external_medical_api.second_limit', 5))
                ->by((string) ($request->bearerToken() ?: $request->ip()));
        });

        RateLimiter::for('external-medical-api-minute', function ($request) {
            return Limit::perMinute((int) config('services.external_medical_api.minute_limit', 80))
                ->by((string) ($request->bearerToken() ?: $request->ip()));
        });

        RateLimiter::for('external-medical-api-daily', function ($request) {
            return Limit::perDay((int) config('services.external_medical_api.daily_limit', 100))
                ->by((string) ($request->bearerToken() ?: $request->ip()));
        });

        Passport::setClientUuids(true);
    }
}
