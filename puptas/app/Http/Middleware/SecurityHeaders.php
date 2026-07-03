<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // The SAR preview route (/admin/sar/{id}/preview) is intentionally loaded inside
        // an <iframe> within the admin panel (same origin). It gets SAMEORIGIN instead of DENY
        // so the browser permits it while still blocking all external domains.
        $isSarPreview = $request->is('admin/sar/*/preview');

        $frameAncestors = $isSarPreview ? "'self'" : "'none'";
        $xFrameOptions  = $isSarPreview ? 'SAMEORIGIN' : 'DENY';

        // Define the Content Security Policy
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://chatwoot-production-49b7.up.railway.app",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com",
            "img-src 'self' data: https: blob:",
            "connect-src 'self' https://chatwoot-production-49b7.up.railway.app wss://chatwoot-production-49b7.up.railway.app",
            "frame-src 'self' blob: https://chatwoot-production-49b7.up.railway.app",
            "frame-ancestors {$frameAncestors}",
        ];

        // Apply security headers to the response
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        $response->headers->set('X-Frame-Options', $xFrameOptions);
        $response->headers->set('X-Content-Type-Options', 'nosniff'); // Prevent MIME sniffing
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload'); // HSTS

        // Remove informational headers that could leak server details
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
