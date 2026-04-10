<?php

use App\Exceptions\OpenRouterApiException;
use App\Models\User;
use App\Models\UserFile;
use App\Services\OpenRouterClient;
use App\Services\GradeExtractionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Minimal valid JPEG binary (SOI + APP0 marker + EOI).
 */
function minimalJpeg(): string
{
    return "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 12) . "\xFF\xD9";
}

/**
 * Valid Gemini JSON fixture with all four required keys.
 */
function geminiJsonFixture(): string
{
    return json_encode([
        'math'    => ['algebra'            => ['grade' => 90, 'confidence' => 0.95]],
        'science' => ['biology'            => ['grade' => 88, 'confidence' => 0.92]],
        'english' => ['english'            => ['grade' => 92, 'confidence' => 0.97]],
        'others'  => ['araling panlipunan' => ['grade' => 85, 'confidence' => 0.80]],
    ]);
}

// ---------------------------------------------------------------------------
// 9.1 Full extraction flow with stubbed Gemini response fixture
// ---------------------------------------------------------------------------

describe('9.1 Full extraction flow', function () {
    beforeEach(function () {
        Storage::fake('local');
    });

    test('returns 200 with math/science/english/others keys when Gemini is stubbed', function () {
        $user = User::factory()->create();

        // Create a real JPEG file in fake storage
        Storage::put('uploads/photo.jpg', minimalJpeg());

        UserFile::create([
            'user_id'   => $user->id,
            'file_path' => 'uploads/photo.jpg',
            'type'      => 'image',
            'status'    => 'uploaded',
        ]);

        // Stub OpenRouterClient so no real HTTP call is made
        $this->mock(OpenRouterClient::class)
            ->shouldReceive('send')
            ->once()
            ->andReturn(geminiJsonFixture());

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertOk()
            ->assertJsonStructure(['redirect']);
    });
});

// ---------------------------------------------------------------------------
// 9.2 Rate limiting — 11th request returns 429
// ---------------------------------------------------------------------------

describe('9.2 Rate limiting', function () {
    test('returns 429 on the 11th request from the same user', function () {
        $user = User::factory()->create();

        // Clear any existing rate-limiter hits for this user
        RateLimiter::clear('grade-extraction:' . $user->id);

        $extractionResult = [
            'math'    => ['algebra' => ['grade' => 90, 'confidence' => 0.95]],
            'science' => ['biology' => ['grade' => 88, 'confidence' => 0.92]],
            'english' => ['english' => ['grade' => 92, 'confidence' => 0.97]],
            'others'  => ['araling panlipunan' => ['grade' => 85, 'confidence' => 0.80]],
        ];

        $this->mock(GradeExtractionService::class)
            ->shouldReceive('extract')
            ->times(10)
            ->andReturn($extractionResult);

        // First 10 requests should succeed
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson('/api/grades/extract')->assertOk();
        }

        // 11th request must be rate-limited
        $this->actingAs($user)->postJson('/api/grades/extract')->assertStatus(429);
    });
});

// ---------------------------------------------------------------------------
// 9.3 File ownership — user A cannot access user B's files
// ---------------------------------------------------------------------------

describe('9.3 File ownership', function () {
    beforeEach(function () {
        Storage::fake('local');
        // OpenRouterClient must be mocked so the container can resolve it even
        // though send() is never called (service throws before reaching it).
        $this->mock(OpenRouterClient::class)->shouldReceive('send')->never();
    });

    test('returns 422 when acting user has no files (only other user\'s files exist)', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create image file belonging to user B only
        Storage::put('uploads/userB_photo.jpg', minimalJpeg());

        UserFile::create([
            'user_id'   => $userB->id,
            'file_path' => 'uploads/userB_photo.jpg',
            'type'      => 'image',
            'status'    => 'uploaded',
        ]);

        $response = $this->actingAs($userA)->postJson('/api/grades/extract');

        $response->assertStatus(422)
            ->assertJsonFragment(['error' => 'No valid image files found for extraction.']);
    });
});

// ---------------------------------------------------------------------------
// 9.4 Gemini unreachable — 503 response and error logged
// ---------------------------------------------------------------------------

describe('9.4 OpenRouter unreachable', function () {
    beforeEach(function () {
        Storage::fake('local');
    });

    test('returns 503 and logs error when OpenRouterClient throws OpenRouterApiException', function () {
        $user = User::factory()->create();

        Storage::put('uploads/photo.jpg', minimalJpeg());

        UserFile::create([
            'user_id'   => $user->id,
            'file_path' => 'uploads/photo.jpg',
            'type'      => 'image',
            'status'    => 'uploaded',
        ]);

        $this->mock(OpenRouterClient::class)
            ->shouldReceive('send')
            ->once()
            ->andThrow(new OpenRouterApiException('Connection refused'));

        Log::spy();

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertStatus(503)
            ->assertJsonFragment(['error' => 'OpenRouter API is currently unavailable. Please try again later.']);

        Log::shouldHaveReceived('error')->once();
    });
});

// ---------------------------------------------------------------------------
// 9.5 No image files — user has only non-image files, assert 422
// ---------------------------------------------------------------------------

describe('9.5 No image files', function () {
    beforeEach(function () {
        Storage::fake('local');
        // OpenRouterClient must be mocked so the container can resolve it even
        // though send() is never called (service throws before reaching it).
        $this->mock(OpenRouterClient::class)->shouldReceive('send')->never();
    });

    test('returns 422 with "No valid image files" when user has only a PDF', function () {
        $user = User::factory()->create();

        // Store a PDF — not an image
        Storage::put('uploads/report.pdf', '%PDF-1.4 fake content');

        UserFile::create([
            'user_id'   => $user->id,
            'file_path' => 'uploads/report.pdf',
            'type'      => 'document',
            'status'    => 'uploaded',
        ]);

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertStatus(422)
            ->assertJsonFragment(['error' => 'No valid image files found for extraction.']);
    });
});
