<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust proxies for HTTPS detection behind Railway's reverse proxy
        $middleware->trustProxies(at: '*');

        // Replace Jetstream's ShareInertiaData with a lightweight version
        // to prevent $user->toArray() from loading all relationships on every request
        $middleware->replace(
            \Laravel\Jetstream\Http\Middleware\ShareInertiaData::class,
            \App\Http\Middleware\ShareInertiaData::class,
        );
        
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\RefreshIdpToken::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            // Add any routes that should be exempt from CSRF protection
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'client' => \Laravel\Passport\Http\Middleware\CheckClientCredentials::class,
            'medical.webhook' => \App\Http\Middleware\VerifyMedicalWebhookSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, Request $request) {
            return app(\App\Exceptions\Handler::class)->render($request, $e);
        });
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        // Do not schedule tokens:prune-expired until the command prunes using
        // a column/criterion that reflects final token-record expiry rather than
        // the access-token expires_at used by middleware.
    })->create();
