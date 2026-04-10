<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicantDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role_id !== 1) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $profile = $user->applicantProfile;
        $strand = $profile?->strand ? strtolower(trim($profile->strand)) : null;

        $gradeRouteMap = [
            'abm'   => '/grades/abm',
            'ict'   => '/grades/ict',
            'humss' => '/grades/humss',
            'gas'   => '/grades/gas',
            'stem'  => '/grades/stem',
            'tvl'   => '/grades/tvl',
        ];

        return Inertia::render('Dashboard/Applicant', [
            'user' => $user,
            'gradeUrl' => $strand ? ($gradeRouteMap[$strand] ?? null) : null,
        ]);
    }
}
