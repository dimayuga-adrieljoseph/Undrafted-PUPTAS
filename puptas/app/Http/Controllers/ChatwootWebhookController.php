<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatwootWebhookController extends Controller
{
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

            // Extract message content from Chatwoot webhook
            $messageContent = $request->input('content');
            $conversationId = $request->input('conversation.id');
            $messageType = $request->input('message_type');
            $accountId = $request->input('account.id');
            $inboxId = $request->input('inbox.id');
            
            // Only respond to incoming messages (not outgoing)
            if ($messageType !== 'incoming') {
                return response()->json(['status' => 'ignored', 'reason' => 'not_incoming_message']);
            }

            // Validate message content
            if (empty($messageContent)) {
                return response()->json(['error' => 'No message content provided'], 400);
            }

            // Get AI response from Gemini
            $aiResponse = $this->getGeminiResponse($messageContent);

            // Optionally send response back to Chatwoot
            if ($conversationId && config('services.chatwoot.access_token')) {
                $this->sendMessageToChatwoot($conversationId, $accountId, $aiResponse);
            }

            // Return the AI response
            return response()->json([
                'content' => $aiResponse,
                'conversation_id' => $conversationId,
                'status' => 'success'
            ]);

        } catch (\Exception $e) {
            Log::error('Chatwoot webhook error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to process message',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $secretKey = config('services.chatwoot.secret_key');
        
        // If no secret is configured, skip verification
        if (empty($secretKey)) {
            return true;
        }

        $signature = $request->header('X-Chatwoot-Signature');
        
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secretKey);

        return hash_equals($expectedSignature, $signature);
    }

    private function sendMessageToChatwoot(int $conversationId, ?int $accountId, string $message): void
    {
        try {
            $baseUrl = config('services.chatwoot.base_url');
            $accessToken = config('services.chatwoot.access_token');

            if (empty($baseUrl) || empty($accessToken)) {
                Log::info('Chatwoot credentials not configured, skipping message send');
                return;
            }

            $url = rtrim($baseUrl, '/') . "/api/v1/accounts/{$accountId}/conversations/{$conversationId}/messages";

            $response = Http::withHeaders([
                'api_access_token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'content' => $message,
                'message_type' => 'outgoing',
                'private' => false,
            ]);

            if ($response->successful()) {
                Log::info('Message sent to Chatwoot successfully', ['conversation_id' => $conversationId]);
            } else {
                Log::error('Failed to send message to Chatwoot', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending message to Chatwoot: ' . $e->getMessage());
        }
    }

    private function getGeminiResponse(string $userMessage): string
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.0-flash-exp');

        if (empty($apiKey)) {
            throw new \Exception('Gemini API key not configured');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(30)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $userMessage]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
            ]
        ]);

        if (!$response->successful()) {
            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Failed to get response from Gemini API');
        }

        $data = $response->json();
        
        // Extract text from Gemini response
        $aiText = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not generate a response.';

        return $aiText;
    }
}
