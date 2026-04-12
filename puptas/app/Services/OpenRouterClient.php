<?php

namespace App\Services;

use App\Exceptions\OpenRouterApiException;
use Illuminate\Support\Facades\Http;

class OpenRouterClient
{
    private string $apiKey;
    private string $endpoint;
    private string $model;

    public function __construct()
    {
        $this->apiKey   = config('services.openrouter.key');
        $this->endpoint = config('services.openrouter.endpoint');
        $this->model    = config('services.openrouter.model');

        if (empty($this->apiKey) || empty($this->endpoint) || empty($this->model)) {
            throw new \RuntimeException(
                'OpenRouter configuration is incomplete: key, endpoint, and model are required.'
            );
        }
    }

    /**
     * Send images and a prompt to OpenRouter and return the raw text response.
     *
     * @param  array<int, array{mime_type: string, data: string}>  $images  Base64-encoded image parts
     * @param  string  $prompt  The text prompt to send alongside the images
     * @return string  The raw text from choices[0].message.content
     *
     * @throws OpenRouterApiException on HTTP errors or connection failures
     */
    public function send(array $images, string $prompt): string
    {
        $content = [];

        foreach ($images as $image) {
            $content[] = [
                'type'      => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $image['mime_type'] . ';base64,' . $image['data'],
                ],
            ];
        }

        $content[] = ['type' => 'text', 'text' => $prompt];

        $body = [
            'model'      => $this->model,
            'max_tokens' => 1500,
            'messages'   => [
                ['role' => 'user', 'content' => $content],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => config('app.url'),
                'X-Title'       => config('app.name'),
            ])->post($this->endpoint, $body);
        } catch (\Throwable $e) {
            throw new OpenRouterApiException(
                'OpenRouter API connection failed: ' . $e->getMessage(),
                0,
                ''
            );
        }

        if ($response->failed()) {
            $status = $response->status();
            $responseBody = $response->body();

            if ($status === 401) {
                throw new OpenRouterApiException(
                    'OpenRouter API authentication failed: invalid API key.',
                    401,
                    $responseBody
                );
            }

            if ($status === 429) {
                throw new OpenRouterApiException(
                    'OpenRouter API rate limit exceeded.',
                    429,
                    $responseBody
                );
            }

            if ($status === 503) {
                throw new OpenRouterApiException(
                    'OpenRouter model is currently unavailable.',
                    503,
                    $responseBody
                );
            }

            throw new OpenRouterApiException(
                'OpenRouter API returned HTTP ' . $status . ': ' . $responseBody,
                $status,
                $responseBody
            );
        }

        return $response->json('choices.0.message.content') ?? '';
    }
}
