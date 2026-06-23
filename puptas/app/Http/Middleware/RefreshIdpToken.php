<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class RefreshIdpToken
{
    /**
     * Handle an incoming request.
     * Check if the IDP access token is expired and silently refresh it if possible.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only run if the user is logged in
        if (!Auth::check()) {
            return $next($request);
        }

        // Skip IDP checks completely if we are logged in via local bypass or emergency login
        if (session('local_bypass') || session('emergency_logged_in')) {
            return $next($request);
        }

        // Skip IDP checks completely if the system is in emergency IDP-down mode.
        // This is much safer than relying on session variables which can sometimes
        // be lost during Auth::login() regeneration on specific staging environments.
        $isEmergencyMode = \Illuminate\Support\Facades\Cache::remember('idp_down_emergency_mode', 30, function() {
            $setting = \App\Models\SystemSetting::where('key', 'idp_down_emergency_login_enabled')->first();
            return $setting && $setting->value === '1';
        });

        if ($isEmergencyMode) {
            return $next($request);
        }

        // Skip IDP checks completely if the system is in emergency IDP-down mode.
        // This is much safer than relying on session variables which can sometimes
        // be lost during Auth::login() regeneration on specific staging environments.
        $isEmergencyMode = \Illuminate\Support\Facades\Cache::remember('idp_down_emergency_mode', 30, function() {
            $setting = \App\Models\SystemSetting::where('key', 'idp_down_emergency_login_enabled')->first();
            return $setting && $setting->value === '1';
        });

        if ($isEmergencyMode) {
            return $next($request);
        }

        // Skip IDP checks completely if the system is in emergency IDP-down mode.
        // This is much safer than relying on session variables which can sometimes
        // be lost during Auth::login() regeneration on specific staging environments.
        try {
            $isEmergencyMode = \Illuminate\Support\Facades\Cache::remember('idp_down_emergency_mode', 30, function() {
                $setting = \App\Models\SystemSetting::where('key', 'idp_down_emergency_login_enabled')->first();
                return $setting && $setting->value === '1';
            });
        } catch (\Throwable $e) {
            // Fallback directly to DB if Redis is offline
            $setting = \App\Models\SystemSetting::where('key', 'idp_down_emergency_login_enabled')->first();
            $isEmergencyMode = $setting && $setting->value === '1';
        }

        if ($isEmergencyMode) {
            return $next($request);
        }

        // Skip IDP session verification/refresh entirely if Redis client classes are not available
        // if (!class_exists('Redis') && !class_exists('Predis\Client')) {
        //     return $next($request);
        // }

        $user = Auth::user();

        try {
            $tokenData = \Illuminate\Support\Facades\Cache::store('redis')->get("idp_tokens:user_{$user->id}");
        } catch (\Throwable $e) {
            // Redis unavailable locally (common in dev environments) - skip silently and proceed
            Log::debug('Redis connection failed in RefreshIdpToken. Skipping token refresh: ' . $e->getMessage());
            return $next($request);
        }

        // If no token record or refresh token, just proceed (or fail depending on strictness)
        if (!$tokenData || empty($tokenData['refresh_token'])) {
            return $next($request);
        }
        
        $expiresAt = isset($tokenData['expires_at']) ? \Carbon\Carbon::createFromTimestamp($tokenData['expires_at']) : null;

        // If we still have time on the clock, proceed normally
        if ($expiresAt && $expiresAt->isFuture()) {
            return $next($request);
        }

        // Token is expired (or close to it) - try to refresh
        Log::info('Attempting to refresh expired IDP token in middleware targeting Redis');
        
        $idpConfig = config('services.idp');
        
        // Use the IDP refresh endpoint
        $refreshUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/auth/refresh';
        
        $refreshPayload = [
            'client_id' => $idpConfig['client_id'],
            'client_secret' => $idpConfig['client_secret'],
            'refresh_token' => $tokenData['refresh_token'],
            'grant_type' => 'refresh_token',
        ];

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->post($refreshUrl, $refreshPayload);

            if ($response->successful()) {
                $newTokenData = $response->json();
                $expiresIn = (int) ($newTokenData['expires_in'] ?? 3600);
                $newExpiresAt = now()->addSeconds($expiresIn - 60);
                
                // Update Redis with new tokens
                \Illuminate\Support\Facades\Cache::store('redis')->put(
                    "idp_tokens:user_{$user->id}",
                    [
                        'access_token'  => $newTokenData['access_token'] ?? $tokenData['access_token'],
                        'refresh_token' => $newTokenData['refresh_token'] ?? $tokenData['refresh_token'],
                        'expires_at'    => $newExpiresAt->timestamp,
                    ],
                    60 * 60 * 24 * 30 // 30 days
                );

                // Set tokens as HttpOnly + Secure cookies so they cannot be read
                // by JavaScript or seen in DevTools. This closes the XSS token-theft vector.
                \Illuminate\Support\Facades\Cookie::queue('access_token', $newTokenData['access_token'] ?? $tokenData['access_token'], $expiresIn / 60, '/', null, true, true);
                \Illuminate\Support\Facades\Cookie::queue('refresh_token', $newTokenData['refresh_token'] ?? $tokenData['refresh_token'], 60 * 24 * 30, '/', null, true, true);
                
                Log::info('Successfully refreshed IDP access token via Redis storage');
            } else {
                Log::error('Failed to refresh IDP token, killing active session for security', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                // Refresh token is dead; kill the local session gracefully
                \Illuminate\Support\Facades\Cache::store('redis')->forget("idp_tokens:user_{$user->id}");
                Auth::logout();
                session()->flush();
                return redirect('/login')->withErrors(['idp' => 'Your session expired. Please log in again.']);
            }
        } catch (\Exception $e) {
             Log::error('Exception while refreshing IDP token', ['error' => $e->getMessage()]);
             
             // If IDP is unreachable, we must log them out to be safe
             Auth::logout();
             session()->flush();
             return redirect('/login')->withErrors(['idp' => 'Unable to verify session due to IDP connection error. Please log in again.']);
        }

        return $next($request);
    }
}
