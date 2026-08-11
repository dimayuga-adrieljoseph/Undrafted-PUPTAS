<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessChatwootWebhookJob implements ShouldQueue
{
    use Queueable;

    public $queue = 'high';

    /**
     * Retry up to 5 times before giving up.
     */
    public int $tries = 5;

    /**
     * Exponential backoff: wait 30s, 60s, 120s, 300s, 600s between retries.
     */
    public array $backoff = [30, 60, 120, 300, 600];

    protected array $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Extract message content from Chatwoot webhook
            $messageContent = $this->payload['content'] ?? null;
            $conversationId = $this->payload['conversation']['id'] ?? null;
            $messageType = $this->payload['message_type'] ?? null;
            $accountId = $this->payload['account']['id'] ?? null;
            
            // Only respond to incoming messages (not outgoing)
            if ($messageType !== 'incoming') {
                return;
            }

            // Validate message content
            if (empty($messageContent)) {
                return;
            }

            // Get AI response from Gemini
            $aiResponse = $this->getGeminiResponse($messageContent);

            // Send response back to Chatwoot
            if ($conversationId && config('services.chatwoot.access_token')) {
                $this->sendMessageToChatwoot((int) $conversationId, $accountId ? (int) $accountId : null, $aiResponse);
            }

        } catch (ConnectionException $e) {
            Log::warning('Chatwoot is unreachable, will retry', [
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Chatwoot webhook job error: ' . $e->getMessage());
            $this->fail($e);
        }
    }

    private function sendMessageToChatwoot(int $conversationId, ?int $accountId, string $message): void
    {
        $baseUrl = config('services.chatwoot.base_url');
        $accessToken = config('services.chatwoot.access_token');

        if (empty($baseUrl) || empty($accessToken)) {
            Log::info('Chatwoot credentials not configured, skipping message send');
            return;
        }

        if (empty($accountId)) {
            Log::warning('Chatwoot account ID missing in payload, skipping message send', [
                'conversation_id' => $conversationId,
            ]);
            return;
        }

        $url = rtrim($baseUrl, '/') . "/api/v1/accounts/{$accountId}/conversations/{$conversationId}/messages";

        // connectTimeout: fail fast if Chatwoot is unreachable (10s)
        // timeout: overall request timeout (15s)
        $response = Http::withHeaders([
            'api_access_token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->connectTimeout(10)->timeout(15)->post($url, [
            'content' => $message,
            'message_type' => 'outgoing',
            'private' => false,
        ]);

        if ($response->successful()) {
            Log::info('Message sent to Chatwoot successfully', ['conversation_id' => $conversationId]);
            return;
        }

        // 5xx means Chatwoot is down or erroring — throw so the job retries
        // 4xx means a config/data problem — log and give up (retrying won't help)
        if ($response->serverError()) {
            throw new \RuntimeException(
                "Chatwoot server error ({$response->status()}) for conversation {$conversationId}: {$response->body()}"
            );
        }

        Log::error('Failed to send message to Chatwoot (client error, will not retry)', [
            'conversation_id' => $conversationId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
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
        
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not generate a response.';
    }
}
