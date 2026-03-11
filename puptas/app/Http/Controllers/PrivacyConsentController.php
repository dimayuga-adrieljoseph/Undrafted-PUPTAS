<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PrivacyConsentController extends Controller
{
    /**
     * Accept privacy consent
     */
    public function accept(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user->update([
            'privacy_consent' => true,
            'privacy_consent_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Check if user has accepted privacy consent
     */
    public function check()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'has_consent' => (bool) $user->privacy_consent,
            'consent_at' => $user->privacy_consent_at,
        ]);
    }
}
