<?php

use App\Exceptions\OpenRouterApiException;
use App\Models\User;
use App\Services\GradeExtractionService;
use Illuminate\Support\Facades\RateLimiter;

// ---------------------------------------------------------------------------
// 6.6 GradeExtractionController
// ---------------------------------------------------------------------------

describe('GradeExtractionController', function () {
    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    test('unauthenticated request returns 401', function () {
        $response = $this->postJson('/api/grades/extract');
        $response->assertUnauthorized();
    });

    // -----------------------------------------------------------------------
    // Successful extraction
    // -----------------------------------------------------------------------

    test('authenticated request returns 200 with redirect key', function () {
        $user = User::factory()->create();

        $extractionResult = [
            'math'    => ['algebra' => ['grade' => 90, 'confidence' => 0.95]],
            'science' => ['biology' => ['grade' => 88, 'confidence' => 0.92]],
            'english' => ['english' => ['grade' => 92, 'confidence' => 0.97]],
            'others'  => ['araling panlipunan' => ['grade' => 85, 'confidence' => 0.80]],
        ];

        $this->mock(GradeExtractionService::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn($extractionResult);

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertOk()->assertJsonStructure(['redirect']);
    });

    // -----------------------------------------------------------------------
    // Parse / validation failure → 422
    // -----------------------------------------------------------------------

    test('returns 422 when service throws InvalidArgumentException (no images)', function () {
        $user = User::factory()->create();

        $this->mock(GradeExtractionService::class)
            ->shouldReceive('extract')
            ->once()
            ->andThrow(new \InvalidArgumentException('No valid image files found for extraction.'));

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertStatus(422)->assertJsonFragment(['error' => 'No valid image files found for extraction.']);
    });

    test('returns 422 when service throws RuntimeException (parse failure)', function () {
        $user = User::factory()->create();

        $this->mock(GradeExtractionService::class)
            ->shouldReceive('extract')
            ->once()
            ->andThrow(new \RuntimeException('OpenRouter response is not valid JSON.'));

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertStatus(422)->assertJsonFragment(['error' => 'OpenRouter response is not valid JSON.']);
    });

    // -----------------------------------------------------------------------
    // Gemini connectivity error → 503
    // -----------------------------------------------------------------------

    test('returns 503 when OpenRouterApiException is thrown', function () {
        $user = User::factory()->create();

        $this->mock(GradeExtractionService::class)
            ->shouldReceive('extract')
            ->once()
            ->andThrow(new OpenRouterApiException('OpenRouter API connection failed'));

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertStatus(503)
            ->assertJsonFragment(['error' => 'OpenRouter API is currently unavailable. Please try again later.']);
    });

    test('logs OpenRouter API error during grade extraction when OpenRouterApiException is thrown', function () {
        $user = User::factory()->create();

        \Illuminate\Support\Facades\Log::spy();

        $this->mock(GradeExtractionService::class)
            ->shouldReceive('extract')
            ->once()
            ->andThrow(new OpenRouterApiException('OpenRouter API connection failed'));

        $this->actingAs($user)->postJson('/api/grades/extract');

        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn($message) => $message === 'OpenRouter API error during grade extraction');
    });

    // -----------------------------------------------------------------------
    // Rate limiting → 429
    // -----------------------------------------------------------------------

    test('returns 429 after exceeding 10 requests per hour', function () {
        $user = User::factory()->create();

        // Exhaust the rate limiter for this user
        $key = 'grade-extraction:' . $user->id;
        RateLimiter::clear($key);

        $extractionResult = [
            'math'    => [],
            'science' => [],
            'english' => [],
            'others'  => [],
        ];

        $this->mock(GradeExtractionService::class)
            ->shouldReceive('extract')
            ->times(10)
            ->andReturn($extractionResult);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson('/api/grades/extract')->assertOk();
        }

        // 11th request should be rate-limited
        $response = $this->actingAs($user)->postJson('/api/grades/extract');
        $response->assertStatus(429);
    });
});
