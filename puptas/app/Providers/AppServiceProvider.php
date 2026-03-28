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

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(UrlGenerator $url): void
    {
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            $url->forceScheme('https');
        }

        // Audit trail — automatically log login & logout events
        Event::listen(Login::class,  LogUserLogin::class);
        Event::listen(Logout::class, LogUserLogout::class);

        RateLimiter::for('external-api-second', function ($request) {
            return Limit::perSecond((int) config('services.external_api.second_limit', 5))
                ->by((string) $request->ip());
        });

        RateLimiter::for('external-api-minute', function ($request) {
            return Limit::perMinute((int) config('services.external_api.minute_limit', 80))
                ->by((string) $request->ip());
        });

        RateLimiter::for('external-api-daily', function ($request) {
            return Limit::perDay((int) config('services.external_api.daily_limit', 1500))
                ->by((string) $request->ip());
        });

        RateLimiter::for('external-program-api-daily', function ($request) {
            return Limit::perDay((int) config('services.external_program_api.daily_limit', 50))
                ->by((string) $request->ip());
        });
    }
}
