<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware to ensure the authenticated user is an Admin or Superadmin.
 * 
 * Admin role_id = 2
 * Superadmin role_id = 7
 */
class EnsureAdmin
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

        // Check if user has admin (2) or superadmin (7) role
        $user = Auth::user();
        
        // Allow admin (2) or superadmin (7)
        if (!in_array($user->role_id, [2, 7])) {
            abort(403, 'Access denied. Admin privileges required.');
        }

        return $next($request);
    }
}
