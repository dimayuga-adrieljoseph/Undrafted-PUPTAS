<?php

/**
 * Property-based tests for OpenRouter migration.
 *
 * Feature: openrouter-migration
 * Properties are tested manually using 100 iterations with random values,
 * as eris/eris is not installed.
 */

use App\Exceptions\OpenRouterApiException;
use App\Services\OpenRouterClient;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

/**
 * Property 1: Exception getter round-trip
 *
 * For any integer status code and string response body, constructing an
 * OpenRouterApiException with those values and calling getStatusCode() and
 * getResponseBody() SHALL return the original values unchanged.
 *
 * Validates: Requirements 2.2
 */
it('Property 1: exception getter round-trip holds for random status codes and bodies', function () {
    for ($i = 0; $i < 100; $i++) {
        $statusCode = rand(PHP_INT_MIN, PHP_INT_MAX);
        $responseBody = bin2hex(random_bytes(rand(1, 64)));

        $ex = new OpenRouterApiException('msg', $statusCode, $responseBody);

        expect($ex->getStatusCode())->toBe($statusCode);
        expect($ex->getResponseBody())->toBe($responseBody);
    }
});

/**
 * Property 2: Generic non-2xx error message prefix
 *
 * For any non-2xx HTTP status code not specifically handled (i.e., not 401, 429, or 503),
 * OpenRouterClient::send() SHALL throw an OpenRouterApiException whose message begins with
 * "OpenRouter API returned HTTP" and whose getStatusCode() returns that status code.
 *
 * Validates: Requirements 4.2, 5.4
 */
it('Property 2: generic non-2xx error message prefix holds for random unhandled status codes', function () {
    config([
        'services.openrouter.key'      => 'test-key',
        'services.openrouter.endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
        'services.openrouter.model'    => 'test-model',
        'app.url'                      => 'https://example.com',
        'app.name'                     => 'TestApp',
    ]);

    $excluded = [401, 429, 503];
    $candidates = array_values(array_filter(range(400, 599), fn ($s) => !in_array($s, $excluded)));
    $image = ['mime_type' => 'image/jpeg', 'data' => base64_encode('img')];

    // Use a shared reference so the closure always returns the current iteration's status
    $currentStatus = null;

    Http::fake(function () use (&$currentStatus) {
        return Http::response('error body', $currentStatus);
    });

    for ($i = 0; $i < 100; $i++) {
        $currentStatus = $candidates[array_rand($candidates)];

        $client = new OpenRouterClient();

        try {
            $client->send([$image], 'prompt');
            $this->fail("Expected OpenRouterApiException for status $currentStatus");
        } catch (OpenRouterApiException $e) {
            expect($e->getMessage())->toStartWith('OpenRouter API returned HTTP');
            expect($e->getStatusCode())->toBe($currentStatus);
        }
    }
});

/**
 * Property 3: Request body structure for any image set
 *
 * For any non-empty array of images, OpenRouterClient::send() SHALL construct a request body
 * containing a messages array with exactly one user message whose content array contains one
 * image_url part per image followed by exactly one text part.
 *
 * Validates: Requirements 6.1
 */
it('Property 3: request body structure is correct for any image set', function () {
    config([
        'services.openrouter.key'      => 'test-key',
        'services.openrouter.endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
        'services.openrouter.model'    => 'test-model',
        'app.url'                      => 'https://example.com',
        'app.name'                     => 'TestApp',
    ]);

    $mimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $capturedBody = null;

    Http::fake(function ($request) use (&$capturedBody) {
        $capturedBody = $request->data();
        return Http::response(['choices' => [['message' => ['content' => 'ok']]]]);
    });

    for ($i = 0; $i < 100; $i++) {
        $count = rand(1, 5);
        $images = [];
        for ($j = 0; $j < $count; $j++) {
            $images[] = [
                'mime_type' => $mimeTypes[array_rand($mimeTypes)],
                'data'      => base64_encode(random_bytes(8)),
            ];
        }

        $capturedBody = null;

        $client = new OpenRouterClient();
        $client->send($images, 'test prompt');

        expect($capturedBody)->not->toBeNull();
        expect($capturedBody['messages'][0]['role'])->toBe('user');

        $content = $capturedBody['messages'][0]['content'];
        expect(count($content))->toBe(count($images) + 1);

        $imageUrlParts = array_filter($content, fn ($p) => $p['type'] === 'image_url');
        expect(count($imageUrlParts))->toBe(count($images));

        $lastPart = end($content);
        expect($lastPart['type'])->toBe('text');
    }
});

/**
 * Property 4: Successful response content extraction
 *
 * For any non-empty string value at choices[0].message.content in a mocked successful API
 * response, OpenRouterClient::send() SHALL return that exact string.
 *
 * Validates: Requirements 6.6
 */
it('Property 4: successful response content extraction returns exact content string', function () {
    config([
        'services.openrouter.key'      => 'test-key',
        'services.openrouter.endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
        'services.openrouter.model'    => 'test-model',
        'app.url'                      => 'https://example.com',
        'app.name'                     => 'TestApp',
    ]);

    $image = ['mime_type' => 'image/jpeg', 'data' => base64_encode('img')];
    $currentContent = null;

    Http::fake(function () use (&$currentContent) {
        return Http::response(['choices' => [['message' => ['content' => $currentContent]]]]);
    });

    for ($i = 0; $i < 100; $i++) {
        $currentContent = bin2hex(random_bytes(rand(1, 32)));

        $client = new OpenRouterClient();
        $result = $client->send([$image], 'prompt');

        expect($result)->toBe($currentContent);
    }
});
