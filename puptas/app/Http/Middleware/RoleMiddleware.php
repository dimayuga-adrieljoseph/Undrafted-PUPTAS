<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
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
            return redirect('/login'); 
        }

        $roles = array_map('intval', $roles);

        // Log information for debugging
        Log::info('RoleMiddleware: Authenticated User ID: ' . Auth::id());
        Log::info('RoleMiddleware: User Role ID: ' . Auth::user()->role_id);
        Log::info('RoleMiddleware: Allowed Roles: ' . implode(',', $roles));

        // Check if the user has an allowed role
        if (!in_array(Auth::user()->role_id, $roles)) {
            Log::error('RoleMiddleware: Unauthorized Access Attempt!');
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
