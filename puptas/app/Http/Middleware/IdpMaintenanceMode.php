<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdpMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIpsConfig = config('services.idp.allowed_ips');
        
        $allowedIps = array_filter(array_map('trim', explode(',', (string) $allowedIpsConfig)));

        if (empty($allowedIps) || !in_array($request->ip(), $allowedIps, true)) {
            return response()->view('errors.idp-maintenance', ['detectedIp' => $request->ip()], 503);
        }

        return $next($request);
    }
}
