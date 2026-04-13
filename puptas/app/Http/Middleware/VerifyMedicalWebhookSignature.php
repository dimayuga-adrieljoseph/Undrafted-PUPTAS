<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        return $next($request);
    }
}
