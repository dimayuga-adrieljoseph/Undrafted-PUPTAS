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
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleIdpCallback(Request $request)
    {
        return app(IdpAuthController::class)->callback($request);
    }
}
