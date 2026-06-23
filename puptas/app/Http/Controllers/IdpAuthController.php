<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IdpAuthController extends Controller
{

    /**
     * Redirect user to IDP login page.
     * 
     * This initiates the OAuth2 authorization flow by redirecting
     * the user to the Identity Provider's login/authorization page.
     * 
     * Required query parameters:
     * - client_id: From config('services.idp.client_id')
     * - redirect_uri: Points to the callback route
     * - response_type: Set to 'code' for Authorization Code flow
     */
    public function login()
    {
        $emergencySetting = \App\Models\SystemSetting::where('key', 'idp_down_emergency_login_enabled')->first();
        if ($emergencySetting && $emergencySetting->value === '1') {
            return redirect()->route('emergency.login');
        }

        \Log::info('IDP login initiated');

        $idpConfig = config('services.idp');

        // Validate IDP configuration
        if (empty($idpConfig) || empty($idpConfig['client_id']) || empty($idpConfig['base_url'])) {
            \Log::error('IDP configuration is missing or incomplete', [
                'config_keys' => $idpConfig ? array_keys($idpConfig) : 'config not found',
            ]);

            return redirect('/auth/idp/error')->withErrors([
                'idp' => 'IDP configuration is missing. Please contact administrator.'
            ]);
        }

        // Generate state for CSRF protection.
        // Only create a new state if one is not already pending in the session.
        // Re-using the existing state prevents a race condition where multiple
        // page refreshes each overwrite the state, causing the callback from the
        // original redirect to fail validation and letting the user retry until
        // a stateless callback is accepted.
        if (!session()->has('idp_oauth_state')) {
            session(['idp_oauth_state' => Str::random(40)]);
        }
        $state = session('idp_oauth_state');

        $baseRedirectUri = $idpConfig['redirect_uri'] ?? route('idp.callback');

        $authorizeQuery = [
            'client_id'     => $idpConfig['client_id'],
            'response_type' => 'code',
            'redirect_uri'  => $baseRedirectUri,
            'state'         => $state,
        ];

        // Construct the full authorization URL using configurable path
        $authorizePath = $idpConfig['authorize_path'] ?? '/api/v1/auth/authorize';

        $authorizeUrl = rtrim($idpConfig['base_url'], '/') . $authorizePath . '?' . http_build_query($authorizeQuery);

        // Log the full URL so it's visible in Railway for debugging
        \Log::info('IDP redirecting to authorize URL: ' . $authorizeUrl);

        // Use standard external redirect instead of Inertia::location
        return redirect()->away($authorizeUrl);
    }

    /**
     * Legacy redirect method - redirects to the login method.
     * Kept for backward compatibility.
     */
    public function redirect()
    {
        return $this->login();
    }

    /**
     * Handle the OAuth2 callback from the IDP.
     * 
     * IDP sends user back here with ?code=xxxx
     * This method exchanges the code for tokens and stores the access token in session.
     */
    public function callback(Request $request)
    {
        // Log callback without sensitive OAuth data
        \Log::info('IDP callback reached', [
            'ip' => $request->ip(),
            'has_code' => $request->has('code'),
            'has_state' => $request->has('state'),
            'request_id' => $request->header('X-Request-ID'),
        ]);

        // Validate state parameter for CSRF protection
        // WORKAROUND: The IDP drops `state` so we cannot strictly enforce it.
        $receivedState = $request->query('state');
        $sessionState = session('idp_oauth_state');

        if (empty($receivedState)) {
            if (empty($sessionState)) {
                \Log::error('IDP callback rejected: missing state parameter and no pending session state', [
                    'ip'         => $request->ip(),
                ]);

                return redirect('/auth/idp/error')->withErrors([
                    'idp' => 'Authentication failed: invalid callback. Please try logging in again.',
                ]);
            }
            \Log::warning('IDP returned no state, relying on session existence as fallback CSRF protection');
        } elseif ($receivedState !== $sessionState) {
            \Log::warning('IDP callback state mismatch or missing', [
                'ip'                  => $request->ip(),
                'user_agent'          => $request->userAgent(),
                'has_session_state'   => !empty($sessionState),
                'received_state_hash' => substr(hash('sha256', (string) $receivedState), 0, 12),
                'session_state_hash'  => !empty($sessionState) ? substr(hash('sha256', (string) $sessionState), 0, 12) : null,
            ]);

            return response('Forbidden: Invalid state parameter', 403);
        }

        // Remove state from session after successful validation
        session()->forget('idp_oauth_state');

        // Extract the authorization code from query parameters
        $code = $request->query('code');

        if (empty($code)) {
            \Log::warning('IDP callback received without authorization code', [
                'ip'           => $request->ip(),
                'query_params' => $request->query(),
                'full_url'     => $request->fullUrl(),
            ]);

            return redirect('/auth/idp/error')->withErrors([
                'idp' => 'Authorization code is missing. Please try signing in again.',
            ]);
        }

        $idpConfig = config('services.idp');

        // Validate IDP configuration
        if (empty($idpConfig) || empty($idpConfig['base_url']) || empty($idpConfig['client_id']) || empty($idpConfig['client_secret'])) {
            \Log::error('IDP configuration is incomplete during callback', [
                'config_keys' => $idpConfig ? array_keys($idpConfig) : 'config not found',
            ]);

            return redirect('/auth/idp/error')->withErrors([
                'idp' => 'IDP configuration is incomplete. Please contact administrator.',
            ]);
        }

        try {
            // Build the token endpoint URL using configurable path
            $tokenPath = $idpConfig['token_path'] ?? '/api/v1/auth/token';
            $tokenUrl = rtrim($idpConfig['base_url'], '/') . $tokenPath;

            \Log::info('Exchanging authorization code for tokens', [
                'token_url' => $tokenUrl,
                'client_id' => $idpConfig['client_id'],
            ]);

            // Reconstruct the exact redirect_uri used in the authorization request
            $baseRedirectUri = $idpConfig['redirect_uri'] ?? route('idp.callback');

            // Prepare the token request payload
            $tokenPayload = [
                'client_id'     => $idpConfig['client_id'],
                'client_secret' => $idpConfig['client_secret'],
                'code'          => $code,
                'redirect_uri'  => $baseRedirectUri,
            ];

            // Send POST request to IDP token endpoint
            $tokenResponse = Http::acceptJson()
                ->timeout(30)
                ->post($tokenUrl, $tokenPayload);

            // Handle failed token request
            if (!$tokenResponse->successful()) {
                $idpError = $tokenResponse->json('error') ?? 'unknown_error';
                $idpDesc  = $tokenResponse->json('error_description') ?? '';

                \Log::error('IDP token exchange failed', [
                    'status_code'  => $tokenResponse->status(),
                    'error'        => $idpError,
                    'description'  => $idpDesc,
                    'raw_body'     => $tokenResponse->body(),
                    'client_id'    => $idpConfig['client_id'],
                    'token_url'    => $tokenUrl,
                ]);

                return redirect('/auth/idp/error')->withErrors([
                    'idp' => "IDP Error: {$idpError}. Please try signing in again.",
                ]);
            }

            $tokenData = $tokenResponse->json();

            // Extract access token from response
            $accessToken = $tokenData['access_token'] ?? null;

            if (empty($accessToken)) {
                \Log::error('IDP token response missing access_token', [
                    'response_keys' => array_keys($tokenData),
                ]);

                return redirect('/auth/idp/error')->withErrors([
                    'idp' => 'Invalid token response from IDP.',
                ]);
            }

            \Log::info('IDP token exchange successful');

            $refreshToken = $tokenData['refresh_token'] ?? '';
            $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

            // Fetch user info from IDP using the access token
            $userPath = $idpConfig['user_path'] ?? '/api/v1/user';
            $userUrl = rtrim($idpConfig['base_url'], '/') . $userPath;

            \Log::info('Fetching user info from IDP', [
                'user_url' => $userUrl,
            ]);

            $userResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(30)
                ->get($userUrl);

            if (!$userResponse->successful()) {
                $errorBody   = $userResponse->body();
                $errorStatus = $userResponse->status();

                // Log full IDP error details internally — never expose raw server
                // responses to the user (may contain internal IDP error messages or stack traces).
                \Log::error('Failed to fetch user info from IDP', [
                    'status' => $errorStatus,
                    'body'   => $errorBody,
                ]);

                return redirect('/auth/idp/error')->withErrors([
                    'idp' => 'Unable to retrieve your account information. Please try logging in again.',
                ]);
            }

            $idpUser = $userResponse->json();

            // Extract email from IDP
            $idpEmail = $idpUser['email'] ?? null;
            if (!$idpEmail) {
                return redirect('/auth/idp/error')->withErrors(['idp' => 'No email provided by IDP.']);
            }

            // Cross-reference user's email with local database
            $localDbUser = \App\Models\User::where('email', $idpEmail)->first();

            if (!$localDbUser) {
                // Email not found in local DB -> treat as new user/applicant
                \Log::info('Intercepting first-time IDP applicant for registration flow (email not in DB)', ['email' => $idpEmail]);

                $pendingRegUuid = \Illuminate\Support\Str::uuid()->toString();

                \Illuminate\Support\Facades\Cache::store('redis')->put(
                    "pending_tokens:{$pendingRegUuid}",
                    [
                        'access_token'  => $accessToken,
                        'refresh_token' => $refreshToken,
                        'expires_at'    => now()->addSeconds($expiresIn - 60)->timestamp,
                    ],
                    now()->addMinutes(30)
                );

                session(['pending_registration' => [
                    'uuid'          => $pendingRegUuid,
                    'user_id'       => $idpUser['id'] ?? null,
                    'email'         => $idpEmail,
                    'username'      => $idpUser['username'] ?? null,
                ]]);

                return redirect('/register');
            }

            // Check if existing user is an applicant with restricted status
            if ((int) $localDbUser->role_id === 1) {
                $testPasser = \App\Models\TestPasser::where('email', $idpEmail)->first();
                if ($testPasser && in_array($testPasser->passer_status_id, [3, 4])) {
                    $message = $testPasser->passer_status_id === 3 
                        ? 'Login is not available for Unqualified applicants.' 
                        : 'Login is currently closed for Waitlisted applicants. Please wait for further announcements regarding open slots.';
                        
                    return redirect('/auth/idp/error')->withErrors([
                        'idp' => $message,
                    ]);
                }
            }

            // Sync user data on login from IDP.
            // Values are trimmed and length-capped before being written to the DB to prevent
            // oversized or malformed IDP payloads from corrupting local records.
            $updateData = [];

            if (isset($idpUser['first_name'])) {
                $updateData['firstname'] = substr(trim((string) $idpUser['first_name']), 0, 100);
            }
            if (isset($idpUser['last_name'])) {
                $updateData['lastname'] = substr(trim((string) $idpUser['last_name']), 0, 100);
            }
            if (isset($idpUser['middle_name'])) {
                $updateData['middlename'] = substr(trim((string) $idpUser['middle_name']), 0, 100);
            }

            if ($localDbUser->idp_user_id !== ($idpUser['id'] ?? null)) {
                $updateData['idp_user_id'] = $idpUser['id'] ?? null;
            }

            if (!empty($updateData)) {
                $localDbUser->update($updateData);
            }

            // Authenticate the user in the local app securely with the eloquent driver
            \Auth::login($localDbUser);

            // Store IDP tokens in Redis rather than MySQL or cookies
            $expiresAt = now()->addSeconds($expiresIn - 60);
            $ttl = 60 * 60 * 24 * 30; // Keep in Redis for 30 days

            try {
                \Illuminate\Support\Facades\Cache::store('redis')->put(
                    "idp_tokens:user_{$localDbUser->id}",
                    [
                        'access_token'  => $accessToken,
                        'refresh_token' => $refreshToken,
                        'expires_at'    => $expiresAt->timestamp,
                    ],
                    $ttl
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to store IDP tokens in Redis during login', [
                    'error' => $e->getMessage()
                ]);
            }

            $roleId = (int) $localDbUser->role_id;

            \Log::info('User logged in seamlessly via Local DB Match', ['local_user_id' => $localDbUser->id, 'role_id' => $roleId]);

            $response = redirect('/dashboard');
            switch ($roleId) {
                case 1:
                    $response = redirect('/applicant-dashboard');
                    break;
                case 2:
                case 7:
                    $response = redirect('/dashboard');
                    break;
                case 3:
                    $response = redirect('/evaluator-dashboard');
                    break;
                case 4:
                    $response = redirect('/interviewer-dashboard');
                    break;
                case 6:
                    $response = redirect('/record-dashboard');
                    break;
            }

            return $response;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('IDP connection error', [
                'error' => $e->getMessage(),
            ]);

            return redirect('/auth/idp/error')->withErrors([
                'idp' => 'Unable to connect to IDP. Please try again later.',
            ]);
        } catch (\Exception $e) {
            \Log::error('IDP authentication unexpected error', [
                'error' => $e->getMessage(),
            ]);

            return redirect('/auth/idp/error')->withErrors([
                'idp' => 'An unexpected error occurred during IDP login.',
            ]);
        }
    }

    public function logout(Request $request)
    {
        $accessToken = null;
        $idpConfig = config('services.idp');

        // 1. Revoke all tokens of the user in our system
        if (Auth::check()) {
            $user = Auth::user();

            try {
                // Get IDP access token from Redis and remove the record
                $tokenData = \Illuminate\Support\Facades\Cache::store('redis')->get("idp_tokens:user_{$user->id}");
                if ($tokenData) {
                    $accessToken = $tokenData['access_token'] ?? null;
                    \Illuminate\Support\Facades\Cache::store('redis')->forget("idp_tokens:user_{$user->id}");
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to remove IDP tokens from Redis during logout', [
                    'error' => $e->getMessage()
                ]);
            }

            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
        }

        // Remove tokens/sessions in PUPTAS locally
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Queue clearing of application cookies (Safety cleanup)
        \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('access_token'));
        \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('refresh_token'));

        // 2. Send request to IDP logout endpoint
        // Updated based on IDP change #11 (Logout fix)
        if ($accessToken && !empty($idpConfig['base_url'])) {
            $logoutPath = $idpConfig['logout_path'] ?? '/api/v1/auth/logout';
            $logoutUrl = rtrim($idpConfig['base_url'], '/') . $logoutPath;

            try {
                \Log::info('Sending logout request to IDP', ['url' => $logoutUrl]);

                // We try a POST request first as per previous spec
                // Short timeout — the user is already logged out locally.
                // We deliberately do not await or inspect the response so a slow
                // IDP cannot block the user's logout redirect.
                Http::withToken($accessToken)
                    ->acceptJson()
                    ->timeout(3)
                    ->post($logoutUrl, [
                        'client_id' => $idpConfig['client_id'],
                        'base_url'  => config('app.url'),
                    ]);

                // If they've implemented a redirect-based logout fix, we might need to hit it differently,
                // but for now we follow the API-style logout.
            } catch (\Exception $e) {
                \Log::error('IDP Logout API failed', ['error' => $e->getMessage()]);
            }
        }

        // Redirect user back to IDP for a fresh login after logout.
        // Use Inertia::location() for a full-page redirect since logout is triggered via Inertia POST.
        // The session was regenerated above, so we can safely write a new CSRF state into it.
        $postLogoutState = Str::random(40);
        session(['idp_oauth_state' => $postLogoutState]);

        $baseRedirectUri = $idpConfig['redirect_uri'] ?? route('idp.callback');

        $authorizePath = $idpConfig['authorize_path'] ?? '/api/v1/auth/authorize';
        $authorizeQuery = [
            'client_id'     => $idpConfig['client_id'],
            'response_type' => 'code',
            'redirect_uri'  => $baseRedirectUri,
            'state'         => $postLogoutState,
        ];

        $authorizeUrl = rtrim($idpConfig['base_url'], '/') . $authorizePath . '?' . http_build_query($authorizeQuery);

        return \Inertia\Inertia::location($authorizeUrl);
    }

    public function cancelRegistration(Request $request)
    {
        $idpConfig = config('services.idp');
        $accessToken = null;

        if (session()->has('pending_registration')) {
            $uuid = session('pending_registration.uuid');
            if ($uuid) {
                $tokens = \Illuminate\Support\Facades\Cache::store('redis')->get("pending_tokens:{$uuid}");
                if ($tokens) {
                    $accessToken = $tokens['access_token'] ?? null;
                    \Illuminate\Support\Facades\Cache::store('redis')->forget("pending_tokens:{$uuid}");
                }
            }
            session()->forget('pending_registration');
        }

        // Send request to IDP logout endpoint
        if ($accessToken && !empty($idpConfig['base_url'])) {
            $logoutPath = $idpConfig['logout_path'] ?? '/api/v1/auth/logout';
            $logoutUrl = rtrim($idpConfig['base_url'], '/') . $logoutPath;

            try {
                \Log::info('Sending logout request to IDP (Cancel Registration)', ['url' => $logoutUrl]);

                Http::withToken($accessToken)
                    ->acceptJson()
                    ->timeout(15)
                    ->post($logoutUrl, [
                        'client_id'    => $idpConfig['client_id'],
                        'base_url'     => config('app.url')
                    ]);
            } catch (\Exception $e) {
                \Log::error('IDP Logout API failed during registration cancel', ['error' => $e->getMessage()]);
            }
        }

        // Generate CSRF state for the post-cancel redirect to IDP
        if (!session()->has('idp_oauth_state')) {
            session(['idp_oauth_state' => Str::random(40)]);
        }
        $state = session('idp_oauth_state');

        $baseRedirectUri = $idpConfig['redirect_uri'] ?? route('idp.callback');

        $authorizePath = $idpConfig['authorize_path'] ?? '/api/v1/auth/authorize';
        $authorizeQuery = [
            'client_id'     => $idpConfig['client_id'],
            'response_type' => 'code',
            'redirect_uri'  => $baseRedirectUri,
            'state'         => $state,
        ];

        $authorizeUrl = rtrim($idpConfig['base_url'], '/') . $authorizePath . '?' . http_build_query($authorizeQuery);

        return redirect()->away($authorizeUrl);
    }
}
