<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IdpAuthController extends Controller
{
    /**
     * Maps IDP role names to local PUPTAS role_id numbers.
     * Adjust these strings to match exactly what the IDP sends.
     */
    private array $roleMap = [
        'applicant'   => 1,
        'admin'       => 2,
        'evaluator'   => 3,
        'interviewer' => 4,
        'medical'     => 5,
        'registrar'   => 6,
        'superadmin'  => 7,
    ];

    /**
     * Redirect user to IDP login page.
     * 
     * This initiates the OAuth2 authorization flow by redirecting
     * the user to the Identity Provider's login/authorization page.
     */
    public function redirect()
    {
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

        $query = http_build_query([
            'client_id'     => $idpConfig['client_id'],
            'redirect_uri'  => $idpConfig['redirect_uri'],
            'response_type' => 'code',
            'scope'         => 'openid profile email',
        ]);

        // Build the authorization URL - append to base_url
        $authorizeUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/auth/authorize?' . $query;

        \Log::info('Redirecting to IDP authorization endpoint', [
            'authorize_url' => $authorizeUrl,
            'redirect_uri' => $idpConfig['redirect_uri'],
        ]);

        return redirect($authorizeUrl);
    }

    /**
     * Handle the OAuth2 callback from the IDP.
     * 
     * IDP sends user back here with ?code=xxxx
     * This method exchanges the code for an access token, retrieves
     * user information from the IDP, and logs the user into PUPTAS.
     */
    public function callback(Request $request)
    {
        // Validate authorization code exists
        $code = $request->query('code');
        
        if (empty($code)) {
            \Log::warning('IDP callback received without authorization code', [
                'ip' => $request->ip(),
                'query_params' => $request->query(),
            ]);
            
            return redirect('/login')->withErrors([
                'idp' => 'Authentication failed. No authorization code received.'
            ]);
        }

        $idpConfig = config('services.idp');
        
        // Validate IDP configuration
        if (empty($idpConfig) || empty($idpConfig['base_url']) || empty($idpConfig['client_id']) || empty($idpConfig['client_secret'])) {
            \Log::error('IDP configuration is incomplete during callback', [
                'config_keys' => $idpConfig ? array_keys($idpConfig) : 'config not found',
            ]);
            
            return redirect('/login')->withErrors([
                'idp' => 'IDP configuration is incomplete. Please contact administrator.'
            ]);
        }

        // Step 1: Exchange authorization code for access token
        $tokenUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/auth/token';
        
        \Log::info('Exchanging authorization code for access token', [
            'token_url' => $tokenUrl,
        ]);

        try {
            $tokenResponse = Http::timeout(30)->post($tokenUrl, [
                'grant_type'    => 'authorization_code',
                'client_id'     => $idpConfig['client_id'],
                'client_secret' => $idpConfig['client_secret'],
                'redirect_uri'  => $idpConfig['redirect_uri'],
                'code'          => $code,
            ]);

            if ($tokenResponse->failed()) {
                \Log::error('IDP token exchange failed', [
                    'status_code' => $tokenResponse->status(),
                    'response' => $tokenResponse->json(),
                ]);
                
                return redirect('/login')->withErrors([
                    'idp' => 'Authentication failed. Please try again.'
                ]);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;

            if (empty($accessToken)) {
                \Log::error('IDP token response missing access_token', [
                    'response' => $tokenData,
                ]);
                
                return redirect('/login')->withErrors([
                    'idp' => 'Authentication failed. Invalid token response.'
                ]);
            }

            \Log::info('Successfully obtained access token from IDP');

            // Step 2: Use access token to get user info from IDP
            $userInfoUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/user';
            
            \Log::info('Fetching user info from IDP', [
                'user_info_url' => $userInfoUrl,
            ]);

            $userResponse = Http::withToken($accessToken)
                ->timeout(30)
                ->get($userInfoUrl);

            if ($userResponse->failed()) {
                \Log::error('IDP user info request failed', [
                    'status_code' => $userResponse->status(),
                    'response' => $userResponse->json(),
                ]);
                
                return redirect('/login')->withErrors([
                    'idp' => 'Could not retrieve user data. Please try again.'
                ]);
            }

            $idpUser = $userResponse->json();
            
            \Log::info('Received user data from IDP', [
                'idp_user_id' => $idpUser['id'] ?? null,
                'idp_email' => $idpUser['email'] ?? null,
                'idp_role' => $idpUser['role'] ?? null,
            ]);

            // Step 3: Map IDP role name to local role_id
            // Use 'role' field - adjust if IDP uses different field name
            $idpRoleName = strtolower($idpUser['role'] ?? 'applicant');
            $roleId = $this->roleMap[$idpRoleName] ?? 1; // default to applicant if unknown

            \Log::info('Mapped IDP role to local role_id', [
                'idp_role' => $idpRoleName,
                'local_role_id' => $roleId,
            ]);

            // Step 4: Find or create user in PUPTAS database
            // Match by idp_user_id if available, otherwise fallback to email
            $userData = [
                'email'     => $idpUser['email'] ?? null,
                'firstname' => $idpUser['firstname'] ?? $idpUser['name'] ?? '',
                'lastname'  => $idpUser['lastname'] ?? '',
                'role_id'   => $roleId,
                'password'  => bcrypt(Str::random(32)), // random password - never used directly for IDP login
            ];

            // Only include idp_user_id if the column exists
            if (isset($idpUser['id'])) {
                $userData['idp_user_id'] = $idpUser['id'];
            }

            $user = User::updateOrCreate(
                ['email' => $idpUser['email'] ?? ''],
                $userData
            );

            \Log::info('User created or updated from IDP', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role_id' => $user->role_id,
            ]);

            // Step 5: Log the user in
            Auth::login($user, true);

            \Log::info('User logged in via IDP', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            // Step 6: Redirect to correct dashboard based on role
            return redirect('/home');

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('IDP connection error', [
                'error' => $e->getMessage(),
            ]);
            
            return redirect('/login')->withErrors([
                'idp' => 'Unable to connect to IDP. Please try again later.'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('IDP authentication unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect('/login')->withErrors([
                'idp' => 'An unexpected error occurred during authentication.'
            ]);
        }
    }
}