<?php

namespace App\Http\Controllers;

use App\Models\ApplicantProfile;
use App\Models\Grade;
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
            // Check if applicant has already submitted grades
            $hasGrades = Grade::where('user_id', $user->id)->exists();

            if (!$hasGrades) {
                // Get user's strand from applicant profile
                $profile = ApplicantProfile::where('user_id', $user->id)->first();
                $strand = $profile?->strand;

                // If no strand is set, redirect to applicant dashboard
                if ($strand) {
                    $strandUpper = strtoupper(trim($strand));
                    return redirect(match ($strandUpper) {
                        'ABM' => '/grades/abm',
                        'ICT' => '/grades/ict',
                        'HUMSS' => '/grades/humss',
                        'GAS' => '/grades/gas',
                        'STEM' => '/grades/stem',
                        'TVL' => '/grades/tvl',
                        default => '/applicant-dashboard',
                    });
                }
            }

            return redirect('/applicant-dashboard');
        }

        return redirect(match ($roleId) {
            2 => '/dashboard',
            3 => '/evaluator-dashboard',
            4 => '/interviewer-dashboard',
            5 => '/medical-dashboard',
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
