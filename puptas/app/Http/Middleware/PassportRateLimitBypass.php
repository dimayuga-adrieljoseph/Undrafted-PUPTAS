<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to add informative headers when Railway rate limits are hit.
 * This doesn't bypass Railway's limits, but provides better response headers
 * for OAuth clients to implement proper backoff strategies.
 */
class PassportRateLimitBypass
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // If we're hitting rate limits on the OAuth token endpoint,
        // add proper Retry-After headers that Railway doesn't provide
        if ($response->getStatusCode() === 429 && $request->is('oauth/token')) {
            // Add standard rate limit headers
            $response->headers->set('Retry-After', '60');
            $response->headers->set('X-RateLimit-Limit', '300');
            $response->headers->set('X-RateLimit-Remaining', '0');
            $response->headers->set('X-RateLimit-Reset', now()->addMinute()->timestamp);
            
            // Add custom message
            $response->setContent(json_encode([
                'error' => 'too_many_requests',
                'error_description' => 'Rate limit exceeded for OAuth token requests. Please implement token caching and retry after 60 seconds.',
                'retry_after' => 60
            ]));
        }

        return $response;
    }
}
