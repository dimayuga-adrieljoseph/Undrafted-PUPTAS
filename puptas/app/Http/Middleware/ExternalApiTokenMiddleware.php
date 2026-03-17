<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $configuredToken = (string) config('services.external_api.token');
        $incomingToken = (string) $request->bearerToken();

        if ($configuredToken === '' || $incomingToken === '' || ! hash_equals($configuredToken, $incomingToken)) {
            return new JsonResponse([
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
