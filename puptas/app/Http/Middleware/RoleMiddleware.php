<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Enums\RoleId;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Accepted role values are either numeric IDs ("1") or case names
     * ("applicant", "superadmin"). Case names are resolved against the
     * RoleId enum's case names via the `value` fallback defined here.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $allowedIds = array_map(fn (string $role) => self::resolveRoleId($role), $roles);

        if (!in_array((int) Auth::user()->role_id, $allowedIds, true)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }

    /**
     * Resolve a numeric or symbolic role token to a numeric ID.
     */
    private static function resolveRoleId(string $role): int
    {
        if (is_numeric($role)) {
            return (int) $role;
        }

        // Accept both "superadmin" (PascalCase lean) and enum case name.
        foreach (RoleId::cases() as $case) {
            if (strcasecmp($case->name, $role) === 0 || strcasecmp($case->name, str_replace('-', '', $role)) === 0) {
                return $case->value;
            }
        }

        return 0;
    }
}
