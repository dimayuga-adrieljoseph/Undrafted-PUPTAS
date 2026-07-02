<?php

namespace App\Http\Controllers;

use App\Services\EmailTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResendWebhookController extends Controller
{
    public function __construct(
        private readonly EmailTrackingService $emailTrackingService,
    ) {}

    /**
     * Handle incoming Resend webhook events.
     *
     * Verifies the webhook signature using the Svix library (used by Resend),
     * then delegates to EmailTrackingService for status updates.
     */
    public function handle(Request $request): JsonResponse
    {
        // Verify webhook signature
        if (!$this->verifySignature($request)) {
            logger()->warning('[ResendWebhook] Invalid webhook signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $eventType = $payload['type'] ?? null;
        $eventData = $payload['data'] ?? [];
        $resendMessageId = $eventData['email_id'] ?? null;

        logger()->info('[ResendWebhook] Received event', [
            'type'     => $eventType,
            'email_id' => $resendMessageId,
            'to'       => $eventData['to'] ?? null,
            'payload_keys' => array_keys($payload),
            'data_keys'    => array_keys($eventData),
        ]);

        if (!$eventType || !$resendMessageId) {
            return response()->json(['error' => 'Missing event type or email_id'], 422);
        }

        $handled = $this->emailTrackingService->handleResendWebhook(
            $resendMessageId,
            $eventType,
            $eventData,
        );

        return response()->json([
            'received' => true,
            'handled'  => $handled,
        ]);
    }

    /**
     * Verify the Resend webhook signature using Svix headers.
     *
     * Resend uses Svix for webhook delivery. The signature is verified
     * using the webhook secret and the svix-id, svix-timestamp, svix-signature headers.
     */
    private function verifySignature(Request $request): bool
    {
        $secret = config('services.resend.webhook_secret');

        // If no secret configured, fail closed (reject)
        if (!$secret) {
            logger()->warning('[ResendWebhook] No webhook secret configured, rejecting request');
            return false;
        }

        $svixId = $request->header('svix-id');
        $svixTimestamp = $request->header('svix-timestamp');
        $svixSignature = $request->header('svix-signature');

        if (!$svixId || !$svixTimestamp || !$svixSignature) {
            return false;
        }

        // Reject timestamps older than 5 minutes to prevent replay attacks
        $tolerance = 300; // 5 minutes
        if (abs(time() - (int) $svixTimestamp) > $tolerance) {
            return false;
        }

        // The secret from Resend starts with "whsec_" — strip the prefix and base64-decode
        $secretBytes = base64_decode(str_replace('whsec_', '', $secret));

        // Build the signed content: "{svix_id}.{svix_timestamp}.{body}"
        $signedContent = "{$svixId}.{$svixTimestamp}.{$request->getContent()}";

        // Compute expected signature
        $expectedSignature = base64_encode(
            hash_hmac('sha256', $signedContent, $secretBytes, true)
        );

        // Svix sends multiple signatures separated by spaces (versioned: "v1,{sig}")
        $signatures = explode(' ', $svixSignature);

        foreach ($signatures as $versionedSig) {
            $parts = explode(',', $versionedSig, 2);
            if (count($parts) === 2 && hash_equals($expectedSignature, $parts[1])) {
                return true;
            }
        }

        return false;
    }
}
