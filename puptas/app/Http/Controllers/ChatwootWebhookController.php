<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\ChatwootHelper;

class ChatwootWebhookController extends Controller
{
    /**
     * Get Chatwoot widget configuration with identity validation
     */
    public function getWidgetConfig(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        $config = ChatwootHelper::getWidgetConfig($user);
        
        return response()->json($config);
    }

    public function handleMessage(Request $request)
    {
        try {
            // Log incoming webhook data
            Log::info('Chatwoot webhook received', $request->all());

            // Verify webhook signature if secret is configured
            if (!$this->verifyWebhookSignature($request)) {
                Log::warning('Invalid Chatwoot webhook signature');
                return response()->json(['error' => 'Invalid signature'], 403);
            }

            // Dispatch job to process the message asynchronously
            \App\Jobs\ProcessChatwootWebhookJob::dispatch($request->all());

            return response()->json(['status' => 'queued']);

        } catch (\Exception $e) {
            Log::error('Chatwoot webhook error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to queue message',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $secretKey = config('services.chatwoot.secret_key');
        
        // If no secret is configured, fail closed (reject)
        if (empty($secretKey)) {
            Log::warning('Chatwoot webhook secret not configured. Rejecting request.');
            return false;
        }

        $signature = $request->header('X-Chatwoot-Signature');
        
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secretKey);

        return hash_equals($expectedSignature, $signature);
    }
}
