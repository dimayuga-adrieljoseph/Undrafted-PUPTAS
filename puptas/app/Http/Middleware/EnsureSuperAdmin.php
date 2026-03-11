<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware to ensure the authenticated user is a Superadmin.
 * 
 * Superadmin role_id = 7
 */
class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect('/login');
        }

        // Check if user has superadmin role (role_id = 7)
        $user = Auth::user();
        
        if ($user->role_id !== 7) {
            // User is not a superadmin - return 403 Forbidden
            abort(403, 'Access denied. Superadmin privileges required.');
        }

        return $next($request);
    }
}
