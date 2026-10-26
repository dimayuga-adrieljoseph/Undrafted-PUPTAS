<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

class TrustRailwayProxy
{
    /**
     * Handle an incoming request.
     *
     * Railway's edge proxy overwrites X-Real-IP and X-Forwarded-For, placing the
     * real connecting client IP as the leftmost entry in X-Forwarded-For.
     * Railway forwards requests to the application container via its private mesh
     * network (Carrier-Grade NAT / RFC 6598, 100.64.0.0/10 within 100.0.0.0/8).
     *
     * When the immediate peer (REMOTE_ADDR) is in 100.0.0.0/8, we extract and trust
     * that leftmost IP as the connecting client. For any non-Railway peer (local dev,
     * unit tests, or direct internet connections), we ignore X-Forwarded-For to prevent
     * header spoofing.
     */
    public function handle(Request $request, Closure $next)
    {
        $remoteAddr = $request->server->get('REMOTE_ADDR');
        $rawXff = $request->header('X-Forwarded-For') ?? $request->server->get('HTTP_X_FORWARDED_FOR');

        // Store raw pre-resolution values in request attributes for diagnostics
        $request->attributes->set('raw_remote_addr', $remoteAddr);
        $request->attributes->set('raw_x_forwarded_for', $rawXff);

        if ($remoteAddr && IpUtils::checkIp($remoteAddr, '100.0.0.0/8')) {
            if ($rawXff) {
                $ips = array_map('trim', explode(',', $rawXff));
                $clientIp = $ips[0] ?? null;

                if ($clientIp && filter_var($clientIp, FILTER_VALIDATE_IP)) {
                    $request->server->set('REMOTE_ADDR', $clientIp);
                }
            }

            // Preserve HTTPS detection behind Railway's TLS-terminating edge
            if ($request->header('X-Forwarded-Proto') === 'https') {
                $request->server->set('HTTPS', 'on');
            }
        }

        // Never trust X-Forwarded-For from arbitrary/untrusted peers or let Symfony re-parse it
        $request::setTrustedProxies([], 0);
        $request->headers->remove('X-Forwarded-For');
        $request->server->remove('HTTP_X_FORWARDED_FOR');

        // Diagnostic logging: off by default, enabled when LOG_CLIENT_IP_DIAG=true
        if (config('services.status_checker.log_client_ip_diag', false) || env('LOG_CLIENT_IP_DIAG', false)) {
            if ($request->isMethod('POST') && ($request->is('api/public/admission-results*') || $request->is('public/admission-results*'))) {
                Log::info('status_checker_client_ip_diag', [
                    'remote_addr'      => $this->maskIpLastOctet($remoteAddr),
                    'x_forwarded_for'  => $this->maskIpLastOctet($rawXff),
                    'x_real_ip'        => $this->maskIpLastOctet($request->header('X-Real-IP')),
                    'fastly_client_ip' => $this->maskIpLastOctet($request->header('Fastly-Client-Ip')),
                    'cf_connecting_ip' => $this->maskIpLastOctet($request->header('CF-Connecting-IP')),
                    'resolved_ip'      => $this->maskIpLastOctet($request->ip()),
                ]);
            }
        }

        return $next($request);
    }

    /**
     * Mask the last octet of an IP address or comma-separated list of IPs.
     */
    private function maskIpLastOctet(?string $ipString): ?string
    {
        if ($ipString === null || trim($ipString) === '') {
            return null;
        }

        $parts = array_map('trim', explode(',', $ipString));
        $masked = array_map(function (string $ip): string {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $segments = explode('.', $ip);
                $segments[3] = 'xxx';
                return implode('.', $segments);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $segments = explode(':', $ip);
                $segments[count($segments) - 1] = 'xxxx';
                return implode(':', $segments);
            }
            return $ip;
        }, $parts);

        return implode(', ', $masked);
    }
}
