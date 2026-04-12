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
        \Log::info('IDP login initiated');

        $idpConfig = config('services.idp');

        // Validate IDP configuration
        if (empty($idpConfig) || empty($idpConfig['client_id']) || empty($idpConfig['base_url'])) {
            \Log::error('IDP configuration is missing or incomplete', [
                'config_keys' => $idpConfig ? array_keys($idpConfig) : 'config not found',
            ]);

            return redirect('/login')->withErrors([
                'idp' => 'IDP configuration is missing. Please contact administrator.'
            ]);
        }

        // Generate state for CSRF protection
        $state = Str::random(40);
        session(['idp_oauth_state' => $state]);

        // Build authorization query parameters
        // prompt=login forces the IDP to always show its login page,
        // even if the user has an existing IDP session.
        $authorizeQuery = [
            'client_id'     => $idpConfig['client_id'],
            'response_type' => 'code',
            'redirect_uri'  => $idpConfig['redirect_uri'] ?? route('idp.callback'),
            'prompt'        => 'login',
        ];

        // Construct the full authorization URL using configurable path
        $authorizePath = $idpConfig['authorize_path'] ?? '/api/v1/login';
        
        $authorizeUrl = rtrim($idpConfig['base_url'], '/') . $authorizePath . '?' . http_build_query($authorizeQuery);

        return \Inertia\Inertia::location($authorizeUrl);
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
        \Log::info('IDP callback reached', ['params' => $request->all()]);

        // Extract the authorization code from query parameters
        $code = $request->query('code');

        if (empty($code)) {
            \Log::warning('IDP callback received without authorization code', [
                'ip'           => $request->ip(),
                'query_params' => $request->query(),
                'full_url'     => $request->fullUrl(),
            ]);

            return redirect('/login')->withErrors([
                'idp' => 'Authorization code is missing. Please try signing in again.',
            ]);
        }

        $idpConfig = config('services.idp');

        // Validate IDP configuration
        if (empty($idpConfig) || empty($idpConfig['base_url']) || empty($idpConfig['client_id']) || empty($idpConfig['client_secret'])) {
            \Log::error('IDP configuration is incomplete during callback', [
                'config_keys' => $idpConfig ? array_keys($idpConfig) : 'config not found',
            ]);

            return redirect('/login')->withErrors([
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

            // Prepare the token request payload
            $tokenPayload = [
                'client_id'     => $idpConfig['client_id'],
                'client_secret' => $idpConfig['client_secret'],
                'code'          => $code,
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

                return redirect('/login')->withErrors([
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

                return redirect('/login')->withErrors([
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
                $errorBody = $userResponse->body();
                $errorStatus = $userResponse->status();

                \Log::error('Failed to fetch user info from IDP', [
                    'status' => $errorStatus,
                    'body' => $errorBody,
                ]);

                return redirect('/login')->withErrors([
                    'idp' => "IDP User Info Failed (Status: $errorStatus) - $errorBody",
                ]);
            }

            $idpUser = $userResponse->json();

            // Extract email from IDP
            $idpEmail = $idpUser['email'] ?? null;
            if (!$idpEmail) {
                return redirect('/login')->withErrors(['idp' => 'No email provided by IDP.']);
            }

            // Cross-reference user's email with local database
            $localDbUser = \App\Models\User::where('email', $idpEmail)->first();

            if (!$localDbUser) {
                // Email not found in local DB -> treat as new user/applicant
                \Log::info('Intercepting first-time IDP applicant for registration flow (email not in DB)', ['email' => $idpEmail]);

                session(['pending_registration' => [
                    'user_id'       => $idpUser['id'] ?? null,
                    'email'         => $idpEmail,
                    'username'      => $idpUser['username'] ?? null,
                    'access_token'  => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_at'    => now()->addSeconds($expiresIn - 60),
                ]]);

                return redirect('/register');
            }

            // Sync the idp_user_id just in case
            if ($localDbUser->idp_user_id !== ($idpUser['id'] ?? null)) {
                $localDbUser->update(['idp_user_id' => $idpUser['id'] ?? null]);
            }

            // Authenticate the user in the local app securely with the eloquent driver
            \Auth::login($localDbUser);

            // Store IDP tokens in the database rather than cookies/session
            \App\Models\RefreshToken::updateOrCreate(
                ['user_id' => $localDbUser->id],
                [
                    'access_token'  => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_at'    => now()->addSeconds($expiresIn - 60),
                ]
            );

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
                case 5:
                    $response = redirect('/medical-dashboard');
                    break;
                case 6:
                    $response = redirect('/record-dashboard');
                    break;
            }

            return $response
                ->withCookie(cookie('access_token', $accessToken, $expiresIn / 60, null, null, false, false))
                ->withCookie(cookie('refresh_token', $refreshToken, 60 * 24 * 30, null, null, false, false));
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('IDP connection error', [
                'error' => $e->getMessage(),
            ]);

            return redirect('/login')->withErrors([
                'idp' => 'Unable to connect to IDP. Please try again later.',
            ]);
        } catch (\Exception $e) {
            \Log::error('IDP authentication unexpected error', [
                'error' => $e->getMessage(),
            ]);

            return redirect('/login')->withErrors([
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
            
            // Get IDP access token from DB and remove the record
            $tokenRecord = \App\Models\RefreshToken::where('user_id', $user->id)->first();
            if ($tokenRecord) {
                $accessToken = $tokenRecord->access_token;
                $tokenRecord->delete();
            }

            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
        }

        // Remove tokens/sessions in PUPTAS locally
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Queue clearing of application cookies
        \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('access_token'));
        \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('refresh_token'));

        // 2. Send POST request to IDP logout endpoint
        if ($accessToken && !empty($idpConfig['base_url'])) {
            $logoutPath = $idpConfig['logout_path'] ?? '/api/v1/auth/logout';
            $logoutUrl = rtrim($idpConfig['base_url'], '/') . $logoutPath;

            try {
                \Log::info('Sending POST request to IDP logout API', ['url' => $logoutUrl]);

                // Include access_token and base_url in the payload as requested by the IDP team
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->timeout(15)
                    ->post($logoutUrl, [
                        'client_id'    => $idpConfig['client_id'],
                        'access_token' => $accessToken,
                        'base_url'     => config('app.url')
                    ]);

                if (!$response->successful()) {
                    \Log::warning('IDP Logout API returned non-success', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('IDP Logout API failed', ['error' => $e->getMessage()]);
            }
        }



        // Then our system should redirect the user to our landing page
        return redirect('/');
    }
}
