<?php

/**
 * Bug Condition Exploration Tests — Grade Extraction Failure
 *
 * Validates: Requirements 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7
 *
 * These tests encode the EXPECTED (correct) behavior.
 * On UNFIXED code, ALL tests FAIL — proving the bugs exist.
 * On FIXED code, all tests PASS.
 *
 * Counterexamples documented at the bottom of this file after running on unfixed code.
 */

use App\Exceptions\OpenRouterApiException;
use App\Models\User;
use App\Models\UserFile;
use App\Services\OpenRouterClient;
use App\Services\GradeExtractionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// ---------------------------------------------------------------------------
// Sub-case 1.1 — OpenRouterApiException: log must include status_code + response_body
// ---------------------------------------------------------------------------

test('1.1 OpenRouterApiException catch block logs status_code and response_body fields', function () {
    // Bug condition: controller logs only user_id + message, omitting status_code and response_body.
    // On unfixed code this assertion FAILS — proving the bug exists.
    //
    // Validates: Requirement 1.1

    $user = User::factory()->create();

    $exception = new OpenRouterApiException('OpenRouter API returned HTTP 401: {"error":"invalid_api_key"}');

    $this->mock(GradeExtractionService::class)
        ->shouldReceive('extract')
        ->once()
        ->andThrow($exception);

    Log::spy();

    $response = $this->actingAs($user)->postJson('/api/grades/extract');

    $response->assertStatus(503);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($user) {
            return isset($context['user_id'])
                && $context['user_id'] === $user->id
                && isset($context['status_code'])
                && isset($context['response_body']);
        });
});

// ---------------------------------------------------------------------------
// Sub-cases 1.2 / 1.3 / 1.4 / 1.5 — RuntimeException: log must include payload detail
// ---------------------------------------------------------------------------

test('1.2 RuntimeException (invalid JSON) catch block logs payload detail beyond just message', function () {
    // Bug condition: controller logs only user_id + message, omitting the raw response payload
    // as a dedicated structured context field.
    // On unfixed code this assertion FAILS — proving the bug exists.
    //
    // Validates: Requirement 1.2

    $user = User::factory()->create();

    $rawResponse = 'Sorry, I cannot process images.';
    $exception   = new \RuntimeException('Gemini response is not valid JSON.');

    $this->mock(GradeExtractionService::class)
        ->shouldReceive('extract')
        ->once()
        ->andThrow($exception);

    Log::spy();

    $response = $this->actingAs($user)->postJson('/api/grades/extract');

    $response->assertStatus(422);

    // The fix requires a dedicated structured context field (payload, raw_response, or context)
    // beyond just 'user_id' and 'message'. On unfixed code only ['user_id', 'message'] are present.
    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($user) {
            if (!isset($context['user_id']) || $context['user_id'] !== $user->id) {
                return false;
            }
            // Must have a dedicated payload/context field — not just 'message'
            return isset($context['payload'])
                || isset($context['raw_response'])
                || isset($context['context'])
                || isset($context['decoded_structure'])
                || isset($context['offending_entry'])
                || isset($context['subject']);
        });
});

test('1.3 RuntimeException (missing keys) catch block logs payload detail', function () {
    // Bug condition: decoded structure is not logged as a dedicated structured context field.
    // On unfixed code this assertion FAILS — proving the bug exists.
    //
    // Validates: Requirement 1.3

    $user = User::factory()->create();

    $exception = new \RuntimeException(
        'Gemini response missing required keys: math, science, english, others.'
    );

    $this->mock(GradeExtractionService::class)
        ->shouldReceive('extract')
        ->once()
        ->andThrow($exception);

    Log::spy();

    $response = $this->actingAs($user)->postJson('/api/grades/extract');

    $response->assertStatus(422);

    // Must have a dedicated payload/context field — not just 'message'
    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($user) {
            if (!isset($context['user_id']) || $context['user_id'] !== $user->id) {
                return false;
            }
            return isset($context['payload'])
                || isset($context['raw_response'])
                || isset($context['decoded_structure'])
                || isset($context['context'])
                || isset($context['offending_entry'])
                || isset($context['subject']);
        });
});

test('1.4 RuntimeException (invalid entry structure) catch block logs payload detail', function () {
    // Bug condition: offending entry is not logged as a dedicated structured context field.
    // On unfixed code this assertion FAILS — proving the bug exists.
    //
    // Validates: Requirement 1.4

    $user = User::factory()->create();

    $exception = new \RuntimeException(
        'Gemini response has invalid subject entry structure.'
    );

    $this->mock(GradeExtractionService::class)
        ->shouldReceive('extract')
        ->once()
        ->andThrow($exception);

    Log::spy();

    $response = $this->actingAs($user)->postJson('/api/grades/extract');

    $response->assertStatus(422);

    // Must have a dedicated payload/context field — not just 'message'
    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($user) {
            if (!isset($context['user_id']) || $context['user_id'] !== $user->id) {
                return false;
            }
            return isset($context['payload'])
                || isset($context['raw_response'])
                || isset($context['offending_entry'])
                || isset($context['context'])
                || isset($context['decoded_structure'])
                || isset($context['subject']);
        });
});

test('1.5 RuntimeException (out-of-range value) catch block logs subject and value', function () {
    // Bug condition: subject name and out-of-range value are not logged as dedicated context fields.
    // On unfixed code this assertion FAILS — proving the bug exists.
    //
    // Validates: Requirement 1.5

    $user = User::factory()->create();

    $exception = new \RuntimeException(
        "Grade value out of range [0,100] for subject 'algebra': 105"
    );

    $this->mock(GradeExtractionService::class)
        ->shouldReceive('extract')
        ->once()
        ->andThrow($exception);

    Log::spy();

    $response = $this->actingAs($user)->postJson('/api/grades/extract');

    $response->assertStatus(422);

    // Must have a dedicated payload/context field — not just 'message'
    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($user) {
            if (!isset($context['user_id']) || $context['user_id'] !== $user->id) {
                return false;
            }
            return isset($context['payload'])
                || isset($context['subject'])
                || isset($context['context'])
                || isset($context['raw_response'])
                || isset($context['offending_entry'])
                || isset($context['decoded_structure']);
        });
});

// ---------------------------------------------------------------------------
// Sub-case 1.6 — InvalidArgumentException: Log::warning must be called with user_id + file_count
// ---------------------------------------------------------------------------

test('1.6 InvalidArgumentException catch block calls Log::warning with user_id and file_count', function () {
    // Bug condition: no Log:: call exists in the InvalidArgumentException catch block.
    // On unfixed code this assertion FAILS — proving the bug exists.
    //
    // Validates: Requirement 1.6

    $user = User::factory()->create();

    $this->mock(GradeExtractionService::class)
        ->shouldReceive('extract')
        ->once()
        ->andThrow(new \InvalidArgumentException('No valid image files found for extraction.'));

    Log::spy();

    $response = $this->actingAs($user)->postJson('/api/grades/extract');

    $response->assertStatus(422)
        ->assertJsonFragment(['error' => 'No valid image files found for extraction.']);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($user) {
            return isset($context['user_id'])
                && $context['user_id'] === $user->id
                && array_key_exists('file_count', $context);
        });
});

// ---------------------------------------------------------------------------
// Sub-case 1.7 — OpenRouterClient constructor must throw on empty config values
// ---------------------------------------------------------------------------

test('1.7 OpenRouterClient constructor throws descriptive RuntimeException when config values are empty', function () {
    // Bug condition: constructor assigns config values without validation.
    // On unfixed code this assertion FAILS — no exception is thrown.
    //
    // Validates: Requirement 1.7

    config()->set('services.openrouter.key', '');
    config()->set('services.openrouter.endpoint', '');
    config()->set('services.openrouter.model', '');

    expect(fn () => new OpenRouterClient())
        ->toThrow(\RuntimeException::class);
});

/*
 * ---------------------------------------------------------------------------
 * Counterexamples documented after running on UNFIXED code
 * ---------------------------------------------------------------------------
 *
 * 1.1 — FAILED: Log::error context for GeminiApiException contains only
 *        ['user_id', 'message']. Fields 'status_code' and 'response_body' are absent.
 *        Counterexample: Log::shouldHaveReceived('error')->withArgs(fn($msg, $ctx) =>
 *          isset($ctx['status_code']) && isset($ctx['response_body'])) → assertion failed.
 *        Error: "Method error(<Any Arguments>) should be called exactly 1 times but called 0 times."
 *        (The withArgs predicate never matched because status_code/response_body are absent.)
 *
 * 1.2 — FAILED: Log::error context for RuntimeException contains only
 *        ['user_id', 'message']. No dedicated 'payload', 'raw_response', 'context', or
 *        similar structured field is present.
 *        Counterexample: withArgs predicate checking for dedicated payload field → never matched.
 *        Error: "Method error(<Any Arguments>) should be called exactly 1 times but called 0 times."
 *
 * 1.3 — FAILED: Same as 1.2 — decoded structure not present as a dedicated context field.
 *        Error: "Method error(<Any Arguments>) should be called exactly 1 times but called 0 times."
 *
 * 1.4 — FAILED: Same as 1.2 — offending entry not present as a dedicated context field.
 *        Error: "Method error(<Any Arguments>) should be called exactly 1 times but called 0 times."
 *
 * 1.5 — FAILED: Same as 1.2 — subject name and value not present as dedicated context fields.
 *        Error: "Method error(<Any Arguments>) should be called exactly 1 times but called 0 times."
 *
 * 1.6 — FAILED: Log::warning was never called. The InvalidArgumentException catch block
 *        returns a 422 response immediately with no Log:: call at all.
 *        Counterexample: Log::shouldHaveReceived('warning')->once() → 0 calls recorded.
 *        Error: "Method warning(<Any Arguments>) should be called at least 1 times but called 0 times."
 *
 * 1.7 — FAILED: new OpenRouterClient() with empty config values does NOT throw.
 *        Constructor silently assigns empty strings to $apiKey, $endpoint, $model.
 *        Counterexample: expect(fn() => new OpenRouterClient())->toThrow(RuntimeException::class)
 *        → Exception "RuntimeException" not thrown.
 * ---------------------------------------------------------------------------
 */
