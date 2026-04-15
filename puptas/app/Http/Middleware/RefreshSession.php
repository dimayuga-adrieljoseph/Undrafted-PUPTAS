<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * RefreshSession middleware
 *
 * Extends the session lifetime on every authenticated request so that
 * active users are never silently logged out mid-workflow.
 *
 * It also emits a structured warning log whenever a request arrives
 * without a valid session, giving us the diagnostic context needed to
 * understand why the auth middleware is returning 401 / redirecting to
 * login (e.g. session expired, session ID mismatch, cookie not sent).
 */
class RefreshSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();

        // ── Diagnostic logging for unauthenticated requests ──────────────────
        // When Auth::check() is false on a route that requires authentication
        // we log everything that could explain the failure so it shows up in
        // Railway's log stream without needing to reproduce the issue locally.
        if (!Auth::check()) {
            $sessionId      = $session->getId();
            $sessionExists  = $session->has('_token'); // CSRF token is always set on a live session
            $cookieName     = config('session.cookie');
            $cookieSent     = $request->hasCookie($cookieName);

            Log::warning('RefreshSession: unauthenticated request detected', [
                'url'              => $request->fullUrl(),
                'method'           => $request->method(),
                'session_id'       => $sessionId,
                'session_started'  => $sessionExists,
                'session_cookie_name'   => $cookieName,
                'session_cookie_sent'   => $cookieSent,
                'session_driver'   => config('session.driver'),
                'session_lifetime' => config('session.lifetime'),
                'session_domain'   => config('session.domain'),
                'session_secure'   => config('session.secure'),
                'session_same_site' => config('session.same_site'),
                'ip'               => $request->ip(),
                'user_agent'       => $request->userAgent(),
                'is_inertia'       => $request->hasHeader('X-Inertia'),
                'referer'          => $request->header('Referer'),
            ]);

            // Pass through — the auth middleware downstream will handle the
            // redirect/401; we are only here to log.
            return $next($request);
        }

        // ── Session refresh for authenticated users ───────────────────────────
        // Regenerate the session's last-activity timestamp so the session is
        // not swept by the garbage collector while the user is actively working.
        // We do NOT regenerate the session ID here (that would break concurrent
        // Inertia requests); we only touch the lifetime.
        $session->put('_last_refreshed', now()->timestamp);

        // Add a debug response header in non-production environments so
        // developers can confirm the middleware is running and see the session ID.
        $response = $next($request);

        if (config('app.env') !== 'production') {
            $response->headers->set('X-Session-Id', substr($session->getId(), 0, 8) . '…');
            $response->headers->set('X-Session-Refreshed', now()->toIso8601String());
        }

        return $response;
    }
}
