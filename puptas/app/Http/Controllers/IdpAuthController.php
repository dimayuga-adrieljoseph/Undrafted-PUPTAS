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
     * Only sends client_id as per IDP requirements.
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

        // Build authorization URL with ONLY client_id - no redirect_uri, response_type, or scope
        $authorizeUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/auth/authorize?client_id=' . urlencode($idpConfig['client_id']);

        \Log::info('Redirecting to IDP authorization endpoint', [
            'authorize_url' => $authorizeUrl,
            'client_id_sent' => true,
        ]);

        return redirect($authorizeUrl);
    }

    /**
     * Handle the OAuth2 callback from the IDP.
     * 
     * IDP sends user back here with ?code=xxxx
     * This method exchanges the code for tokens and returns them to the user.
     */
    public function callback(Request $request)
    {
        // Capture the authorization code from the callback
        $code = $request->query('code');
        
        if (empty($code)) {
            \Log::warning('IDP callback received without authorization code', [
                'ip' => $request->ip(),
                'query_params' => $request->query(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'missing_authorization_code',
                'message' => 'Authorization code is required.',
            ], 400);
        }

        $idpConfig = config('services.idp');
        
        // Validate IDP configuration
        if (empty($idpConfig) || empty($idpConfig['base_url']) || empty($idpConfig['client_id']) || empty($idpConfig['client_secret'])) {
            \Log::error('IDP configuration is incomplete during callback', [
                'config_keys' => $idpConfig ? array_keys($idpConfig) : 'config not found',
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'invalid_configuration',
                'message' => 'IDP configuration is incomplete.',
            ], 500);
        }

        // Step 1: Exchange authorization code for tokens
        $tokenUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/auth/token';
        
        \Log::info('Exchanging authorization code for tokens', [
            'token_url' => $tokenUrl,
        ]);

        try {
            // Send POST request with only client_id, client_secret, and code
            // Never log client_secret for security
            $tokenResponse = Http::timeout(30)->post($tokenUrl, [
                'grant_type'    => 'authorization_code',
                'client_id'     => $idpConfig['client_id'],
                'client_secret' => $idpConfig['client_secret'],
                'code'          => $code,
            ]);

            if ($tokenResponse->failed()) {
                \Log::error('IDP token exchange failed', [
                    'status_code' => $tokenResponse->status(),
                    'response' => $tokenResponse->json(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'token_exchange_failed',
                    'message' => 'Failed to exchange authorization code for tokens.',
                    'details' => $tokenResponse->json(),
                ], $tokenResponse->status());
            }

            $tokenData = $tokenResponse->json();
            
            // Verify tokens were returned
            $accessToken = $tokenData['access_token'] ?? null;
            $idToken = $tokenData['id_token'] ?? null;

            if (empty($accessToken)) {
                \Log::error('IDP token response missing access_token', [
                    'response_keys' => array_keys($tokenData),
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_token_response',
                    'message' => 'Invalid token response from IDP.',
                ], 500);
            }

            \Log::info('Successfully obtained tokens from IDP', [
                'has_access_token' => !empty($accessToken),
                'has_id_token' => !empty($idToken),
                'token_type' => $tokenData['token_type'] ?? null,
                'expires_in' => $tokenData['expires_in'] ?? null,
            ]);

            // Return tokens to the user
            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $accessToken,
                    'id_token' => $idToken,
                    'token_type' => $tokenData['token_type'] ?? 'Bearer',
                    'expires_in' => $tokenData['expires_in'] ?? null,
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                ],
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('IDP connection error', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'connection_error',
                'message' => 'Unable to connect to IDP. Please try again later.',
            ], 503);
            
        } catch (\Exception $e) {
            \Log::error('IDP authentication unexpected error', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }
}