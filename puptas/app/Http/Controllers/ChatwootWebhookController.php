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

            // Extract message content from Chatwoot webhook
            $messageContent = $request->input('content');
            $conversationId = $request->input('conversation.id');
            $messageType = $request->input('message_type');
            
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
