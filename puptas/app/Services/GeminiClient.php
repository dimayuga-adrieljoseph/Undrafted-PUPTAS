<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiClient
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model  = config('services.gemini.model', 'gemini-2.0-flash');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Gemini API key is not configured.');
        }
    }

    /**
     * Send images and a prompt to the Gemini API and return the raw text response.
     *
     * @param  array<int, array{mime_type: string, data: string}>  $images
     * @param  string  $prompt
     * @return string
     *
     * @throws \RuntimeException on HTTP errors or connection failures
     */
    public function send(array $images, string $prompt): string
    {
        $parts = [];

        foreach ($images as $image) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $image['mime_type'],
                    'data'      => $image['data'],
                ],
            ];
        }

        $parts[] = ['text' => $prompt];

        $body = [
            'contents' => [
                ['parts' => $parts],
            ],
            'generationConfig' => [
                'maxOutputTokens' => 1500,
            ],
        ];

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post("{$endpoint}?key={$this->apiKey}", $body);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Gemini API connection failed: ' . $e->getMessage());
        }

        if ($response->failed()) {
            $status = $response->status();
            $body   = $response->body();

            $message = match ($status) {
                400 => 'Gemini API bad request: ' . $body,
                401, 403 => 'Gemini API authentication failed: invalid API key.',
                429 => 'Gemini API rate limit exceeded.',
                503 => 'Gemini API is currently unavailable.',
                default => "Gemini API returned HTTP {$status}: {$body}",
            };

            throw new \RuntimeException($message);
        }

        return $response->json('candidates.0.content.parts.0.text') ?? '';
    }
}
