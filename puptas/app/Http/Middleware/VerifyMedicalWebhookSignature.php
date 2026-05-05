<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyMedicalWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Validation order is intentional for security and performance:
        // 1. Timestamp check (fast, prevents replay of old requests)
        // 2. Nonce check (fast, prevents replay of recent requests - but NOT stored yet)
        // 3. HMAC signature check (expensive, validates authenticity)
        // 4. Nonce storage (only after authentication succeeds)
        // This ensures we fail fast on cheaper checks before performing expensive HMAC validation,
        // and prevents unauthenticated requests from poisoning the nonce cache.
        
        // Extract and validate timestamp from payload
        $payloadData = $request->json()->all();
        
        if (!isset($payloadData['timestamp'])) {
            return response()->json(['message' => 'Missing timestamp'], 400);
        }
        
        $timestamp = $payloadData['timestamp'];
        $currentTime = time();
        $fiveMinutesInSeconds = 5 * 60;
        
        // Check if timestamp is older than 5 minutes
        if (($currentTime - $timestamp) > $fiveMinutesInSeconds) {
            return response()->json(['message' => 'Request expired'], 403);
        }
        
        // Extract and validate nonce from payload
        if (!isset($payloadData['nonce'])) {
            return response()->json(['message' => 'Missing nonce'], 400);
        }
        
        $nonce = $payloadData['nonce'];
        $cacheKey = 'webhook_nonce_' . $nonce;
        
        // Check if nonce has been seen before (but don't store it yet)
        if (Cache::has($cacheKey)) {
            return response()->json(['message' => 'Duplicate request'], 403);
        }
        
        // Validate HMAC signature before storing nonce
        $signature = $request->header('X-Medical-Signature');

        if (!$signature) {
            return response()->json(['message' => 'Missing Signature'], 403);
        }

        $secret = config('services.medical_webhook.secret');
        
        if (!$secret) {
            return response()->json(['message' => 'Webhook secret not configured'], 500);
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Invalid Signature'], 403);
        }

        // Only store nonce after HMAC validation succeeds
        // This prevents unauthenticated requests from poisoning the cache
        Cache::put($cacheKey, true, 600); // 600 seconds = 10 minutes

        return $next($request);
    }
}
