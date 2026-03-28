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
        if (!Auth::check() || !session()->has('refresh_token')) {
            return $next($request);
        }

        $expiresAt = session('token_expires_at');
        
        // If we still have time on the clock, proceed normally
        if ($expiresAt && $expiresAt > now()->timestamp) {
            return $next($request);
        }

        // Token is expired (or close to it) - try to refresh
        Log::info('Attempting to refresh expired IDP token in middleware');
        
        $idpConfig = config('services.idp');
        
        // Use the IDP refresh endpoint
        $refreshUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/auth/refresh';
        
        $refreshPayload = [
            'client_id' => $idpConfig['client_id'],
            'client_secret' => $idpConfig['client_secret'],
            'refresh_token' => session('refresh_token'),
            'grant_type' => 'refresh_token',
        ];

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->post($refreshUrl, $refreshPayload);

            if ($response->successful()) {
                $tokenData = $response->json();
                $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);
                
                // Update session with new tokens
                session([
                    'access_token' => $tokenData['access_token'] ?? session('access_token'),
                    'refresh_token' => $tokenData['refresh_token'] ?? session('refresh_token'),
                    'token_expires_at' => now()->addSeconds($expiresIn - 60)->timestamp,
                ]);
                
                Log::info('Successfully refreshed IDP access token');
            } else {
                Log::error('Failed to refresh IDP token', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                // Refresh token is dead; kill the local session gracefully
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
