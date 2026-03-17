<?php

namespace App\Http\Controllers;

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

        // Build authorization query parameters (include response_type and state)
        $authorizeQuery = [
            'client_id' => $idpConfig['client_id'],
            'redirect_uri' => $idpConfig['redirect_uri'] ?? null,
            'response_type' => 'code',
            'state' => $state,
        ];

        // Add scope if configured
        if (!empty($idpConfig['scope'])) {
            $authorizeQuery['scope'] = $idpConfig['scope'];
        }

        // Construct the full authorization URL using configurable path
        $authorizePath = $idpConfig['authorize_path'] ?? '/api/v1/auth/authorize';
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

            // Store access token in session
            session([
                'access_token' => $accessToken,
            ]);

            \Log::info('Access token stored in session');

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
                \Log::error('Failed to fetch user info from IDP', [
                    'status' => $userResponse->status(),
                    'body' => $userResponse->body(),
                ]);

                return redirect('/login')->withErrors([
                    'idp' => 'Unable to retrieve user information from IDP.',
                ]);
            }

            $idpUser = $userResponse->json();

            // Map IDP user to local user record
            $localUser = \App\Models\User::where('idp_user_id', $idpUser['id'] ?? null)
                ->orWhere('email', $idpUser['email'] ?? null)
                ->first();

            if (!$localUser) {
                // Create a new local user if one doesn't exist
                $localUser = \App\Models\User::create([
                    'name' => $idpUser['name'] ?? ($idpUser['email'] ?? 'IDP User'),
                    'email' => $idpUser['email'] ?? null,
                    'idp_user_id' => $idpUser['id'] ?? null,
                    // default applicant role (1) if not mapped
                    'role_id' => 1,
                    'password' => bcrypt(Str::random(40)),
                ]);
            } else {
                // Update existing user with latest IDP info
                $localUser->update([
                    'name' => $idpUser['name'] ?? $localUser->name,
                    'email' => $idpUser['email'] ?? $localUser->email,
                    'idp_user_id' => $idpUser['id'] ?? $localUser->idp_user_id,
                ]);
            }

            // Map IDP role/attributes to local role_id
            $roleMapping = [
                // Example mappings - update to match your IDP roles
                'applicant' => 1,
                'admin' => 2,
                'evaluator' => 3,
                'interviewer' => 4,
                'medical' => 5,
                'record' => 6,
                'superadmin' => 7,
            ];

            $idpRole = strtolower($idpUser['role'] ?? ($idpUser['roles'][0] ?? null));
            if ($idpRole && isset($roleMapping[$idpRole])) {
                $localUser->role_id = $roleMapping[$idpRole];
                $localUser->save();
            }

            // Authenticate the user in the local app
            \Auth::login($localUser);

            \Log::info('User logged in via IDP', ['user_id' => $localUser->id, 'idp_user_id' => $localUser->idp_user_id]);

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
}