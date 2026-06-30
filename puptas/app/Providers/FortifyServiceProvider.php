<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Redirect login view to IDP unless there are errors or bypass is requested
        Fortify::loginView(function () {
            $env = strtolower(config('app.env'));
            $isBypassAllowed = in_array($env, ['local', 'staging']) && 
                               (request()->has('local') || session('local_bypass'));

            if ($isBypassAllowed || session()->has('errors') || request()->has('idp_error')) {
                if ($isBypassAllowed) {
                    session(['local_bypass' => true]);
                }
                
                return \Inertia\Inertia::render('Auth/Login', [
                    'canResetPassword' => \Illuminate\Support\Facades\Route::has('password.request'),
                    'status' => session('status'),
                ]);
            }
            return redirect()->route('idp.redirect');
        });

        // Redirect register view to IDP unless bypass is requested
        Fortify::registerView(function () {
            $env = strtolower(config('app.env'));
            $isBypassAllowed = in_array($env, ['local', 'staging']) && 
                               (request()->has('local') || session('local_bypass'));

            if ($isBypassAllowed) {
                session(['local_bypass' => true]);
                
                $email = request()->query('email', 'localapplicant@gmail.com');
                $refNumber = request()->query('ref', '2026-LOCAL-TEST');
                
                // If they are using the default mock, ensure a dummy test passer exists so the form validation passes
                if ($email === 'localapplicant@gmail.com') {
                    \App\Models\TestPasser::firstOrCreate(
                        ['reference_number' => $refNumber],
                        [
                            'first_name' => 'Local',
                            'surname' => 'Applicant',
                            'email' => $email,
                            'passer_status_id' => 1,
                        ]
                    );
                }

                $pendingReg = [
                    'email' => $email,
                    'sub' => 'mock-idp-sub-local-1',
                ];

                // Inject IDP data into the session so the form POST validation reads it
                session(['pending_registration' => $pendingReg]);
                
                // Pass directly to Inertia to bypass middleware timing issue
                return \Inertia\Inertia::render('Auth/Register', [
                    'pending_registration' => $pendingReg,
                    'test_passer_data' => \App\Models\TestPasser::where('email', $email)->first()
                ]);
            }

            // If the user arrived from the IDP callback with a pending registration
            // (i.e., they authenticated with IDP but have no local account yet),
            // render the registration form instead of redirecting back to IDP.
            // Without this check, new users hit an infinite redirect loop:
            //   /register -> IDP -> callback -> /register -> IDP -> ... -> auth error page.
            if (session()->has('pending_registration')) {
                $pendingReg = session('pending_registration');

                return \Inertia\Inertia::render('Auth/Register', [
                    'pending_registration' => $pendingReg,
                    'test_passer_data' => \App\Models\TestPasser::where('email', $pendingReg['email'] ?? '')->first(),
                ]);
            }
            
            return redirect()->route('idp.redirect');
        });

        // Register custom logout response to redirect to IDP
        $this->app->singleton(\Laravel\Fortify\Contracts\LogoutResponse::class, function () {
            return new class implements \Laravel\Fortify\Contracts\LogoutResponse {
                public function toResponse($request)
                {
                    return redirect()->route('idp.redirect');
                }
            };
        });

        // Register custom authenticated session controller for dynamic redirects
        $this->app->singleton(\Laravel\Fortify\Contracts\LoginResponse::class, \App\Http\Controllers\AuthenticatedSessionController::class);
        $this->app->singleton(\Laravel\Fortify\Contracts\RegisterResponse::class, \App\Http\Responses\RegisterResponse::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
