<?php

use App\Exceptions\OpenRouterApiException;
use App\Models\User;
use App\Models\UserFile;
use App\Services\OpenRouterClient;
use App\Services\GradeExtractionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function minimalJpeg(): string
{
    return "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 12) . "\xFF\xD9";
}

// Flat format matching the actual prompt/response structure
function flatGeminiFixture(): string
{
    return json_encode([
        'subjects' => [
            'math'    => ['General Mathematics' => '90'],
            'science' => ['Earth and Life Science' => '88'],
            'english' => ['Oral Communication' => '92'],
            'others'  => ['Filipino' => '85'],
        ],
    ]);
}

// ---------------------------------------------------------------------------
// 9.1 Full extraction flow
// ---------------------------------------------------------------------------

describe('9.1 Full extraction flow', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    test('returns redirect URL with subjects when OpenRouter is stubbed', function () {
        $user = User::factory()->create();

        Storage::disk('public')->put('uploads/photo.jpg', minimalJpeg());

        UserFile::create([
            'user_id'      => $user->id,
            'file_path'    => 'uploads/photo.jpg',
            'type'         => 'photo_2x2',
            'original_name'=> 'photo.jpg',
            'status'       => 'pending',
        ]);

        $this->mock(OpenRouterClient::class)
            ->shouldReceive('send')
            ->once()
            ->andReturn(flatGeminiFixture());

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertOk()->assertJsonStructure(['redirect']);
    });
});

// ---------------------------------------------------------------------------
// 9.2 Rate limiting — 31st request returns 429 (limit is 30/min)
// ---------------------------------------------------------------------------

describe('9.2 Rate limiting', function () {
    test('returns 429 after exceeding 30 requests per minute', function () {
        $user = User::factory()->create();

        $extractionResult = [
            'subjects' => [
                'math'    => ['general mathematics' => 90.0],
                'science' => ['earth and life science' => 88.0],
                'english' => ['oral communication' => 92.0],
                'others'  => ['filipino' => 85.0],
            ],
        ];

        $this->mock(GradeExtractionService::class)
            ->shouldReceive('extract')
            ->times(30)
            ->andReturn($extractionResult);

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)->postJson('/api/grades/extract')->assertOk();
        }

        $this->actingAs($user)->postJson('/api/grades/extract')->assertStatus(429);
    });
});

// ---------------------------------------------------------------------------
// 9.3 File ownership — user A cannot trigger extraction on user B's files
// ---------------------------------------------------------------------------

describe('9.3 File ownership', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->mock(OpenRouterClient::class)->shouldReceive('send')->never();
    });

    test('returns 422 when acting user has no files', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Storage::disk('public')->put('uploads/userB.jpg', minimalJpeg());
        UserFile::create([
            'user_id'      => $userB->id,
            'file_path'    => 'uploads/userB.jpg',
            'type'         => 'photo_2x2',
            'original_name'=> 'userB.jpg',
            'status'       => 'pending',
        ]);

        $this->actingAs($userA)->postJson('/api/grades/extract')
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'No valid image files found for extraction.']);
    });
});

// ---------------------------------------------------------------------------
// 9.4 OpenRouter unreachable — returns 503
// ---------------------------------------------------------------------------

describe('9.4 OpenRouter unreachable', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    test('returns 503 and logs error when OpenRouterClient throws', function () {
        $user = User::factory()->create();

        Storage::disk('public')->put('uploads/photo.jpg', minimalJpeg());
        UserFile::create([
            'user_id'      => $user->id,
            'file_path'    => 'uploads/photo.jpg',
            'type'         => 'photo_2x2',
            'original_name'=> 'photo.jpg',
            'status'       => 'pending',
        ]);

        $this->mock(OpenRouterClient::class)
            ->shouldReceive('send')
            ->once()
            ->andThrow(new OpenRouterApiException('Connection refused', 0, ''));

        Log::spy();

        $this->actingAs($user)->postJson('/api/grades/extract')
            ->assertStatus(503)
            ->assertJsonFragment(['error' => 'OpenRouter API is currently unavailable. Please try again later.']);

        Log::shouldHaveReceived('error')->once();
    });
});

// ---------------------------------------------------------------------------
// 9.5 No image files — only non-image files present
// ---------------------------------------------------------------------------

describe('9.5 No image files', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->mock(OpenRouterClient::class)->shouldReceive('send')->never();
    });

    test('returns 422 when user has only a PDF', function () {
        $user = User::factory()->create();

        Storage::disk('public')->put('uploads/report.pdf', '%PDF-1.4 fake content');
        UserFile::create([
            'user_id'      => $user->id,
            'file_path'    => 'uploads/report.pdf',
            'type'         => 'photo_2x2',
            'original_name'=> 'report.pdf',
            'status'       => 'pending',
        ]);

        $this->actingAs($user)->postJson('/api/grades/extract')
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'No valid image files found for extraction.']);
    });
});

// ---------------------------------------------------------------------------
// 9.6 Malformed AI response — returns 422
// ---------------------------------------------------------------------------

describe('9.6 Malformed AI response', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    test('returns 422 when OpenRouter returns invalid JSON', function () {
        $user = User::factory()->create();

        Storage::disk('public')->put('uploads/photo.jpg', minimalJpeg());
        UserFile::create([
            'user_id'      => $user->id,
            'file_path'    => 'uploads/photo.jpg',
            'type'         => 'photo_2x2',
            'original_name'=> 'photo.jpg',
            'status'       => 'pending',
        ]);

        $this->mock(OpenRouterClient::class)
            ->shouldReceive('send')
            ->once()
            ->andReturn('Sorry, I cannot process that image.');

        $this->actingAs($user)->postJson('/api/grades/extract')
            ->assertStatus(422);
    });

    test('returns 422 when OpenRouter returns JSON missing subjects key', function () {
        $user = User::factory()->create();

        Storage::disk('public')->put('uploads/photo.jpg', minimalJpeg());
        UserFile::create([
            'user_id'      => $user->id,
            'file_path'    => 'uploads/photo.jpg',
            'type'         => 'photo_2x2',
            'original_name'=> 'photo.jpg',
            'status'       => 'pending',
        ]);

        $this->mock(OpenRouterClient::class)
            ->shouldReceive('send')
            ->once()
            ->andReturn(json_encode(['math' => [], 'science' => [], 'english' => [], 'others' => []]));

        $this->actingAs($user)->postJson('/api/grades/extract')
            ->assertStatus(422);
    });
});
