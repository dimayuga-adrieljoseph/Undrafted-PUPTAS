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
     * Applies HTTP security headers to every non-local response to protect
     * against common web vulnerabilities:
     *   - Content-Security-Policy  → XSS / resource injection
     *   - X-Frame-Options          → Clickjacking
     *   - X-Content-Type-Options   → MIME-sniffing
     *   - Strict-Transport-Security → Protocol downgrade / cookie hijacking
     *   - Referrer-Policy          → Referrer leakage
     *   - Permissions-Policy       → Browser feature/API abuse
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // In local development, Vite's HMR and dev server inject various scripts,
        // styles, and WebSockets (often using IPv6 [::1] or dynamic ports).
        // To prevent strict CSP from breaking the local dev experience, we skip
        // these security headers locally. They will still apply in production.
        if (app()->environment('local')) {
            return $response;
        }

        // -----------------------------------------------------------------------
        // X-Frame-Options & CSP frame-ancestors
        // -----------------------------------------------------------------------
        // The SAR preview route (/admin/sar/{id}/preview) is intentionally loaded
        // inside an <iframe> within the admin panel (same origin). It gets
        // SAMEORIGIN instead of DENY so the browser permits it while still blocking
        // all external domains.
        $isSarPreview = $request->is('admin/sar/*/preview');

        $frameAncestors = $isSarPreview ? "'self'" : "'none'";
        $xFrameOptions  = $isSarPreview ? 'SAMEORIGIN' : 'DENY';

        // -----------------------------------------------------------------------
        // Content-Security-Policy
        // -----------------------------------------------------------------------
        $scriptSrc  = "'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://chatwoot-production-49b7.up.railway.app";
        $styleSrc   = "'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com";
        $imgSrc     = "'self' data: https: blob:";
        $connectSrc = "'self' https://chatwoot-production-49b7.up.railway.app wss://chatwoot-production-49b7.up.railway.app";

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "style-src {$styleSrc}",
            "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com",
            "img-src {$imgSrc}",
            "connect-src {$connectSrc}",
            "frame-src 'self' blob: https://chatwoot-production-49b7.up.railway.app",
            "frame-ancestors {$frameAncestors}",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "upgrade-insecure-requests",
        ]);

        // -----------------------------------------------------------------------
        // Permissions-Policy
        // Restrict access to sensitive browser APIs. Only grant what the app
        // actually needs; deny everything else to limit the blast radius of XSS.
        // -----------------------------------------------------------------------
        $permissionsPolicy = implode(', ', [
            'accelerometer=()',
            'ambient-light-sensor=()',
            'autoplay=()',
            'battery=()',
            'camera=()',
            'display-capture=()',
            'document-domain=()',
            'encrypted-media=()',
            'fullscreen=(self)',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'payment=()',
            'picture-in-picture=()',
            'publickey-credentials-get=()',
            'screen-wake-lock=()',
            'sync-xhr=()',
            'usb=()',
            'xr-spatial-tracking=()',
        ]);

        // -----------------------------------------------------------------------
        // Apply all security headers
        // -----------------------------------------------------------------------
        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', $xFrameOptions);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // HSTS: tell browsers to always use HTTPS for 1 year, including subdomains.
        // The 'preload' directive opts the domain into the browser preload list.
        // NOTE: Only set this after you are 100% sure the site runs on HTTPS.
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // Referrer-Policy: send only the origin (no path/query) on cross-origin
        // requests, and the full URL for same-origin requests.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy: restrict browser feature access.
        $response->headers->set('Permissions-Policy', $permissionsPolicy);

        // -----------------------------------------------------------------------
        // Remove informational headers that could leak server details
        // -----------------------------------------------------------------------
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
