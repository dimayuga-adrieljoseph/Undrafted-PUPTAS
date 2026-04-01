<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ExternalMedicalApiTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $validToken = config('services.external_medical_api.token');

        if (!$token || $token !== $validToken) {
            return response()->json([
                'error' => 'Unauthorized.',
                'message' => 'Invalid or missing API token.'
            ], 401);
        }

        return $next($request);
    }
}
