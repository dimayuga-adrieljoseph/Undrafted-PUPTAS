<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            // Log the failure with enough context to diagnose session/cookie issues.
            // This fires after RefreshSession has already logged the session state,
            // so together they give a complete picture of why auth is failing.
            Log::warning('RoleMiddleware: auth check failed — redirecting to login', [
                'url'            => $request->fullUrl(),
                'required_roles' => $roles,
                'session_id'     => $request->session()->getId(),
                'cookie_present' => $request->hasCookie(config('session.cookie')),
                'ip'             => $request->ip(),
                'is_inertia'     => $request->hasHeader('X-Inertia'),
            ]);

            return redirect('/login');
        }

        $roles = array_map('intval', $roles);

        if (!in_array(Auth::user()->role_id, $roles)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
