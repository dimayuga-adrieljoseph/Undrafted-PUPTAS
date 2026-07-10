<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse;

class AuthenticatedSessionController implements LoginResponse
{
    /**
     * Where to redirect users after login.
     */
    public function toResponse($request)
    {
        $user = Auth::user();
        $roleId = $user->role_id;

        // Re-persist local_bypass after login so RefreshIdpToken skips Redis on the next request
        $env = strtolower(config('app.env'));
        if (in_array($env, ['local', 'staging']) && $request->session()->get('local_bypass')) {
            $request->session()->put('local_bypass', true);
        }

        if ($roleId == 1) {
            $testPasser = \App\Models\TestPasser::where('email', $user->email)->first();
            if ($testPasser && in_array($testPasser->passer_status_id, [3, 4])) {
                // Allow login if the applicant has already submitted an application.
                // Once submitted, they need access to track status, upload documents, etc.
                $hasSubmittedApplication = \App\Models\Application::where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->whereNotNull('submitted_at')
                    ->exists();

                if (!$hasSubmittedApplication) {
                    $cutoffService = app(\App\Services\CutoffSettingsService::class);
                    $isScoreOverride = $cutoffService->isScoreAllowed((float) $testPasser->pupcet_total_score);
                    $isEmailOverride = $cutoffService->isEmailAllowed($user->email);

                    if (!$isScoreOverride && !$isEmailOverride) {
                        $message = $testPasser->passer_status_id === 3 
                            ? 'Login is not available for Unqualified applicants.' 
                            : 'Login is currently closed for Waitlisted applicants. Please wait for further announcements regarding open slots.';
                        
                        Auth::guard('web')->logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();

                        return redirect('/auth/idp/error')->withErrors([
                            'idp' => $message,
                        ]);
                    }
                }
            }

            return redirect('/applicant-dashboard');
        }

        return redirect(match ((int) $roleId) {
            2, 7 => '/dashboard',
            3, 8 => '/evaluator-dashboard',
            4 => '/interviewer-dashboard',
            6 => '/record-dashboard',
            default => '/',
        });
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
