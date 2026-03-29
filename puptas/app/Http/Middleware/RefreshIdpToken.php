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

        $user = Auth::user();
        $tokenRecord = \App\Models\RefreshToken::where('user_id', $user->id)->first();

        // If no token record or refresh token, just proceed (or fail depending on strictness)
        if (!$tokenRecord || !$tokenRecord->refresh_token) {
            return $next($request);
        }
        
        // If we still have time on the clock, proceed normally
        if ($tokenRecord->expires_at && $tokenRecord->expires_at->isFuture()) {
            return $next($request);
        }

        // Token is expired (or close to it) - try to refresh
        Log::info('Attempting to refresh expired IDP token in middleware targeting DB');
        
        $idpConfig = config('services.idp');
        
        // Use the IDP refresh endpoint
        $refreshUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/auth/refresh';
        
        $refreshPayload = [
            'client_id' => $idpConfig['client_id'],
            'client_secret' => $idpConfig['client_secret'],
            'refresh_token' => $tokenRecord->refresh_token,
            'grant_type' => 'refresh_token',
        ];

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->post($refreshUrl, $refreshPayload);

            if ($response->successful()) {
                $tokenData = $response->json();
                $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);
                
                // Update DB with new tokens
                $tokenRecord->update([
                    'access_token' => $tokenData['access_token'] ?? $tokenRecord->access_token,
                    'refresh_token' => $tokenData['refresh_token'] ?? $tokenRecord->refresh_token,
                    'expires_at' => now()->addSeconds($expiresIn - 60),
                ]);

                \Illuminate\Support\Facades\Cookie::queue('access_token', $tokenRecord->access_token, $expiresIn / 60, null, null, false, false);
                \Illuminate\Support\Facades\Cookie::queue('refresh_token', $tokenRecord->refresh_token, 60 * 24 * 30, null, null, false, false);
                
                Log::info('Successfully refreshed IDP access token via Database storage');
            } else {
                Log::error('Failed to refresh IDP token, killing active session for security', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                // Refresh token is dead; kill the local session gracefully
                $tokenRecord->delete();
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
