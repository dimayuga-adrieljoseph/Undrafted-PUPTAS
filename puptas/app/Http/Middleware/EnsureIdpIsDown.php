<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use App\Models\SystemSetting;

class EnsureIdpIsDown
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idpStatusDown = Cache::get('idp_status_down', false);
        
        $emergencySetting = SystemSetting::where('key', 'idp_down_emergency_login_enabled')->first();
        $isManualOverride = $emergencySetting && $emergencySetting->value === '1';

        if (!$idpStatusDown && !$isManualOverride) {
            // IDP is up and no manual override is active. We block access to the emergency login.
            return redirect('/')->with('error', 'The emergency login is currently disabled because the IDP is online.');
        }

        return $next($request);
    }
}
