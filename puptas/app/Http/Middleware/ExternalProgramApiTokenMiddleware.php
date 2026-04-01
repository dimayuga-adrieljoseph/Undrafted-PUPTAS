<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalProgramApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $configuredToken = (string) config('services.external_program_api.token');
        $incomingToken = (string) $request->bearerToken();

        if ($configuredToken === '' || $incomingToken === '' || ! hash_equals($configuredToken, $incomingToken)) {
            app(AuditLogService::class)->logActivity(
                'AUTH_FAILED',
                'External API',
                sprintf(
                    'Denied external program API request to %s from IP %s (invalid/missing token).',
                    $request->path(),
                    $request->ip() ?? 'unknown'
                ),
                null,
                AuditLog::CATEGORY_AUTHENTICATION
            );

            return new JsonResponse([
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
