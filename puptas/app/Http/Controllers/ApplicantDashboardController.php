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

    return Inertia::render('ApplicantDashboard', [
        'user' => $user,
    ]);
        
    }
}
