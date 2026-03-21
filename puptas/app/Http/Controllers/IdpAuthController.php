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

        // Build authorization query parameters - only client_id as requested
        $authorizeQuery = [
            'client_id' => $idpConfig['client_id'],
        ];

        // Note: state is still generated and stored in session for potential verification on callback

        // Construct the full authorization URL using configurable path
        // IDP Swagger docs or frontend requires pointing to /login
        $authorizePath = $idpConfig['authorize_path'] ?? '/login';
        $authorizeUrl = rtrim($idpConfig['base_url'], '/') . $authorizePath . '?' . http_build_query($authorizeQuery);

        \Log::info('Redirecting to IDP authorization endpoint', [
            'authorize_url' => $authorizeUrl,
            'client_id_sent' => true,
            'redirect_uri' => $idpConfig['redirect_uri'] ?? null,
        ]);

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

            \Log::info('Successfully obtained access token from IDP', [
                'has_access_token' => !empty($accessToken),
                'token_type'       => $tokenData['token_type'] ?? null,
                'expires_in'       => $tokenData['expires_in'] ?? null,
            ]);

            $refreshToken = $tokenData['refresh_token'] ?? null;
            $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

            // Store tokens and expiration timestamp in session
            session([
                'access_token'     => $accessToken,
                'refresh_token'    => $refreshToken,
                'token_expires_at' => now()->addSeconds($expiresIn - 60)->timestamp, // Refresh 60 seconds before actual expiration
            ]);

            \Log::info('Access and refresh tokens stored in session');

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

            // Map IDP role/attributes to local integer role_id
            $roleMapping = [
                'applicant'   => 1,
                'admin'       => 2,
                'evaluator'   => 3,
                'interviewer' => 4,
                'medical'     => 5,
                'record'      => 6,
                'superadmin'  => 7,
            ];

            // Robust multi-format role extraction
            $idpRole = 'applicant';

            if (!empty($idpUser['role'])) {
                $idpRole = is_array($idpUser['role']) ? ($idpUser['role']['name'] ?? $idpUser['role']['title'] ?? 'applicant') : $idpUser['role'];
            } elseif (!empty($idpUser['roles']) && is_array($idpUser['roles'])) {
                $firstRole = $idpUser['roles'][0];
                $idpRole = is_array($firstRole) ? ($firstRole['name'] ?? $firstRole['role_name'] ?? 'applicant') : $firstRole;
            } elseif (!empty($idpUser['role_name'])) {
                $idpRole = $idpUser['role_name'];
            } elseif (!empty($idpUser['user_type'])) {
                $idpRole = $idpUser['user_type'];
            }

            $idpRole = strtolower(is_string($idpRole) ? $idpRole : 'applicant');
            $roleId = $roleMapping[$idpRole] ?? 1;

            // Failsafe: Log the raw payload if it defaulted to applicant to help debug
            if ($roleId === 1) {
                \Log::warning('IDP Role matched Applicant or defaulted. Raw IDP User payload:', ['idpUser' => $idpUser]);

                // Check for new applicants requiring onboarding
                $hasProfile = \App\Models\ApplicantProfile::where('user_id', $idpUser['id'])->exists();

                if (!$hasProfile) {
                    \Log::info('Intercepting first-time IDP applicant for registration flow', ['id' => $idpUser['id']]);

                    // Temporarily store just enough session data to bind the profile
                    session(['pending_registration' => [
                        'user_id' => $idpUser['id'] ?? null,
                        'email'   => $idpUser['email'] ?? null,
                        'username' => $idpUser['username'] ?? null,
                    ]]);

                    // Do not log them in yet. Send them to complete the profile.
                    return redirect('/register');
                }
            }

            // Build the virtual user profile array
            $idpUserProfile = [
                'idp_user_id' => $idpUser['id'] ?? null,
                'name'        => $idpUser['name'] ?? ($idpUser['email'] ?? 'IDP User'),
                'email'       => $idpUser['email'] ?? null,
                'role_id'     => $roleId,
                'role_name'   => $idpRole,
            ];

            // If not an applicant, upsert the staff profile for the User Management Dashboard
            if ($roleId !== 1) {
                \App\Models\StaffProfile::updateOrCreate(
                    ['user_id' => $idpUserProfile['idp_user_id']],
                    [
                        'name'      => $idpUserProfile['name'],
                        'email'     => $idpUserProfile['email'],
                        'role_id'   => $idpUserProfile['role_id'],
                        'role_name' => $idpUserProfile['role_name'],
                    ]
                );
            }

            // Store the entire profile solidly in session for the IdpUserProvider to retrieve
            session(['idp_user_profile' => $idpUserProfile]);

            // Instantiate our virtual Authenticatable user
            $localUser = new \App\Auth\IdpUser($idpUserProfile);

            // Authenticate the user in the local app securely with the idp driver
            \Auth::login($localUser);

            \Log::info('User logged in seamlessly via Database-less IDP', ['idp_user_id' => $localUser->idp_user_id, 'role_id' => $localUser->role_id]);

            // Redirect user to appropriate dashboard based on role
            switch ($localUser->role_id) {
                case 1:
                    return redirect('/applicant-dashboard');
                case 2:
                case 7:
                    return redirect('/dashboard');
                case 3:
                    return redirect('/evaluator-dashboard');
                case 4:
                    return redirect('/interviewer-dashboard');
                case 5:
                    return redirect('/medical-dashboard');
                case 6:
                    return redirect('/record-dashboard');
                default:
                    return redirect('/dashboard');
            }
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
        Auth::logout();

        $request->session()->forget([
            'idp_user_profile',
            'access_token',
            'refresh_token',
            'token_expires_at',
        ]);

        $request->session()->regenerate();

        $idpLogoutUrl = config('services.idp.logout_url');

        if ($idpLogoutUrl) {
            $redirectBack = urlencode(url('/login'));
            return redirect()->away("{$idpLogoutUrl}?post_logout_redirect_uri={$redirectBack}");
        }

        return redirect('/login');
    }
}
