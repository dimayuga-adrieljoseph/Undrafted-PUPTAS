<?php

use App\Exceptions\OpenRouterApiException;
use App\Services\OpenRouterClient;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function () {
    config([
        'services.openrouter.key'      => 'test-key',
        'services.openrouter.endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
        'services.openrouter.model'    => 'test-model',
        'app.url'                      => 'https://example.com',
        'app.name'                     => 'TestApp',
    ]);
});

$sampleImage = ['mime_type' => 'image/jpeg', 'data' => base64_encode('fake-image')];

it('includes all four required headers on every request', function () use ($sampleImage) {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    $client = new OpenRouterClient();
    $client->send([$sampleImage], 'prompt');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer test-key')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->hasHeader('HTTP-Referer', 'https://example.com')
            && $request->hasHeader('X-Title', 'TestApp');
    });
});

it('throws OpenRouterApiException with correct message, status, and body on HTTP 401', function () use ($sampleImage) {
    Http::fake(['*' => Http::response('Unauthorized', 401)]);

    $client = new OpenRouterClient();

    try {
        $client->send([$sampleImage], 'prompt');
        $this->fail('Expected OpenRouterApiException');
    } catch (OpenRouterApiException $e) {
        expect($e->getMessage())->toBe('OpenRouter API authentication failed: invalid API key.');
        expect($e->getStatusCode())->toBe(401);
        expect($e->getResponseBody())->toBe('Unauthorized');
    }
});

it('throws OpenRouterApiException with correct message, status, and body on HTTP 429', function () use ($sampleImage) {
    Http::fake(['*' => Http::response('Too Many Requests', 429)]);

    $client = new OpenRouterClient();

    try {
        $client->send([$sampleImage], 'prompt');
        $this->fail('Expected OpenRouterApiException');
    } catch (OpenRouterApiException $e) {
        expect($e->getMessage())->toBe('OpenRouter API rate limit exceeded.');
        expect($e->getStatusCode())->toBe(429);
        expect($e->getResponseBody())->toBe('Too Many Requests');
    }
});

it('throws OpenRouterApiException with correct message, status, and body on HTTP 503', function () use ($sampleImage) {
    Http::fake(['*' => Http::response('Service Unavailable', 503)]);

    $client = new OpenRouterClient();

    try {
        $client->send([$sampleImage], 'prompt');
        $this->fail('Expected OpenRouterApiException');
    } catch (OpenRouterApiException $e) {
        expect($e->getMessage())->toBe('OpenRouter model is currently unavailable.');
        expect($e->getStatusCode())->toBe(503);
        expect($e->getResponseBody())->toBe('Service Unavailable');
    }
});

it('throws OpenRouterApiException with message beginning "OpenRouter API connection failed:" on connection failure', function () use ($sampleImage) {
    Http::fake(function () {
        throw new \Exception('Connection refused');
    });

    $client = new OpenRouterClient();

    try {
        $client->send([$sampleImage], 'prompt');
        $this->fail('Expected OpenRouterApiException');
    } catch (OpenRouterApiException $e) {
        expect($e->getMessage())->toStartWith('OpenRouter API connection failed:');
    }
});

it('returns choices[0].message.content on successful response', function () use ($sampleImage) {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'extracted grades']]]])]);

    $client = new OpenRouterClient();
    $result = $client->send([$sampleImage], 'prompt');

    expect($result)->toBe('extracted grades');
});
