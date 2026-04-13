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

        if ($roleId == 1) {
            return redirect('/applicant-dashboard');
        }

        return redirect(match ($roleId) {
            2 => '/dashboard',
            3 => '/evaluator-dashboard',
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
