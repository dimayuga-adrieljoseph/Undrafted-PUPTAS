<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class CallbackController extends Controller
{
    /**
     * Whitelist of allowed API endpoints to prevent CSRF gadget attacks.
     * Only these endpoints can be called from the callback page.
     */
    private const ALLOWED_API_ENDPOINTS = [
        '/api/callback',
        // Add other safe endpoints here as needed
    ];

    /**
     * Display the callback loading page.
     * 
     * This page is used as an intermediary page that shows a loading screen
     * while making an API call. Once the API responds, it redirects to the
     * appropriate page.
     */
    public function index(Request $request)
    {
        // Get the API endpoint from query string, validate against whitelist
        $requestedApi = $request->get('api', '/api/callback');
        $apiEndpoint = $this->validateApiEndpoint($requestedApi);
        
        // Get redirect URLs and validate they are internal paths only
        $redirectTo = $this->validateRedirectUrl($request->get('redirect', '/dashboard'));
        $errorRedirect = $this->validateRedirectUrl($request->get('error', '/login'));

        return Inertia::render('Callback', [
            'apiEndpoint' => $apiEndpoint,
            'redirectTo' => $redirectTo,
            'errorRedirect' => $errorRedirect,
        ]);
    }

    /**
     * Validate API endpoint against whitelist to prevent CSRF gadget attacks.
     * 
     * @param string $endpoint
     * @return string Validated endpoint or default if invalid
     */
    private function validateApiEndpoint(string $endpoint): string
    {
        // Only allow whitelisted endpoints
        if (in_array($endpoint, self::ALLOWED_API_ENDPOINTS, true)) {
            return $endpoint;
        }
        
        // Log suspicious attempts
        \Log::warning('Attempted to use non-whitelisted API endpoint in callback', [
            'requested_endpoint' => $endpoint,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        
        // Fall back to safe default
        return '/api/callback';
    }

    /**
     * Validate redirect URL to prevent open redirect attacks.
     * Only allows internal application paths.
     * 
     * @param string $url
     * @return string Validated URL or safe default
     */
    private function validateRedirectUrl(string $url): string
    {
        // Ensure URL is not empty
        if (empty($url)) {
            return '/dashboard';
        }
        
        // Reject any URL that contains a scheme (http://, https://, etc.)
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $url)) {
            \Log::warning('Attempted open redirect with absolute URL', [
                'requested_url' => $url,
                'ip' => request()->ip(),
            ]);
            return '/dashboard';
        }
        
        // Reject protocol-relative URLs (//example.com)
        if (str_starts_with($url, '//')) {
            \Log::warning('Attempted open redirect with protocol-relative URL', [
                'requested_url' => $url,
                'ip' => request()->ip(),
            ]);
            return '/dashboard';
        }
        
        // Reject URLs with @ symbol (could indicate username in URL)
        if (str_contains($url, '@')) {
            \Log::warning('Attempted redirect with @ symbol', [
                'requested_url' => $url,
                'ip' => request()->ip(),
            ]);
            return '/dashboard';
        }
        
        // Ensure URL starts with / for internal path
        if (!str_starts_with($url, '/')) {
            return '/' . ltrim($url, './');
        }
        
        // URL is a valid internal path
        return $url;
    }

    /**
     * Handle the actual API callback.
     * 
     * This is the endpoint that will be called from the frontend.
     * Connect your API logic here.
     */
    public function handle(Request $request)
    {
        $response = Http::post($request->input('api_url'), $request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'Callback processed successfully',
            'data' => $request->all(),
        ]);
    }

    /**
     * Handle OAuth2 callback from Identity Provider.
     * 
     * Receives the authorization code from the IDP redirect and exchanges
     * it for an access token.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleIdpCallback(Request $request)
    {
        // Extract the authorization code from the query parameter
        $code = $request->query('code');

        // Log the authorization code for debugging
        \Log::info('OAuth2 callback received with authorization code', [
            'code' => $code,
            'has_code' => !empty($code),
        ]);

        // Validate that the code parameter exists
        if (empty($code)) {
            \Log::warning('OAuth2 callback received without authorization code', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'query_params' => $request->query(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'missing_authorization_code',
                'message' => 'Authorization code is required for OAuth2 callback.',
                'code' => $code,
            ], 400);
        }

        // Get IDP configuration from services config
        $idpConfig = config('services.idp');
        
        // Validate IDP configuration exists
        if (empty($idpConfig) || empty($idpConfig['base_url']) || empty($idpConfig['client_id']) || empty($idpConfig['client_secret'])) {
            \Log::error('OAuth2 configuration is missing or incomplete', [
                'idp_config' => $idpConfig ? array_keys($idpConfig) : 'config not found',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'invalid_configuration',
                'message' => 'OAuth2 provider configuration is missing or incomplete.',
                'code' => $code,
            ], 500);
        }

        // Build the token endpoint URL
        $tokenUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/auth/token';

        try {
            // Send POST request to token endpoint
            $response = Http::timeout(30)->post($tokenUrl, [
                'client_id' => $idpConfig['client_id'],
                'client_secret' => $idpConfig['client_secret'],
                'code' => $code,
            ]);

            // Check if the request was successful
            if ($response->successful()) {
                $tokenData = $response->json();

                \Log::info('OAuth2 token exchange successful', [
                    'has_access_token' => isset($tokenData['access_token']),
                    'has_refresh_token' => isset($tokenData['refresh_token']),
                    'token_type' => $tokenData['token_type'] ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $tokenData,
                ]);
            }

            // Handle error response from IDP
            $errorData = $response->json();
            $errorMessage = $errorData['error'] ?? $errorData['message'] ?? 'Token exchange failed';

            \Log::warning('OAuth2 token exchange failed', [
                'status_code' => $response->status(),
                'error' => $errorMessage,
                'response' => $errorData,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'token_exchange_failed',
                'message' => $errorMessage,
                'details' => $errorData,
                'code' => $code,
            ], $response->status());

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Handle connection timeout or network errors
            \Log::error('OAuth2 token exchange connection error', [
                'error' => $e->getMessage(),
                'token_url' => $tokenUrl,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'connection_error',
                'message' => 'Failed to connect to OAuth2 provider. Please try again later.',
                'code' => $code,
            ], 503);

        } catch (\Exception $e) {
            // Handle any other unexpected errors
            \Log::error('OAuth2 token exchange unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred during OAuth2 callback processing.',
                'code' => $code,
            ], 500);
        }
    }
}
