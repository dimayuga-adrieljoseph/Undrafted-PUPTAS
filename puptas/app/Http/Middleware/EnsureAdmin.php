<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Enums\RoleId;

/**
 * Middleware to ensure the authenticated user is an Admin or Superadmin.
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

        // Allow admin or superadmin
        if (!in_array((int) Auth::user()->role_id, [RoleId::Admin->value, RoleId::SuperAdmin->value], true)) {
            abort(403, 'Access denied. Admin privileges required.');
        }

        return $next($request);
    }
}
