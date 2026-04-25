<?php

use App\Models\ApplicantProfile;
use App\Models\User;
use App\Models\UserFile;
use App\Services\DoclingParser;
use Illuminate\Support\Facades\Log;

// ---------------------------------------------------------------------------
// DoclingGradeExtractionController — updated controller using DoclingParser
// ---------------------------------------------------------------------------

describe('DoclingGradeExtractionController', function () {

    // -----------------------------------------------------------------------
    // 4.1 — No UserFile records with docling_json → fallback + warning log
    // -----------------------------------------------------------------------

    test('returns fallback when no UserFile records have docling_json', function () {
        $user = User::factory()->create();

        // Create a UserFile without docling_json
        UserFile::create([
            'user_id'       => $user->id,
            'type'          => 'report_card',
            'file_path'     => 'uploads/test.jpg',
            'original_name' => 'test.jpg',
            'status'        => 'pending',
            'docling_json'  => null,
        ]);

        Log::spy();

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertOk();
        $response->assertJsonFragment(['fallback' => true]);
        $response->assertJsonStructure(['redirect', 'fallback']);

        Log::shouldHaveReceived('warning')->once();
    });

    // -----------------------------------------------------------------------
    // 4.2 — Successful DoclingParser result → session set + redirect returned
    // -----------------------------------------------------------------------

    test('stores extraction result in session and returns redirect on success', function () {
        $user = User::factory()->create();

        // Create a UserFile with docling_json set
        UserFile::create([
            'user_id'       => $user->id,
            'type'          => 'report_card',
            'file_path'     => 'uploads/test.jpg',
            'original_name' => 'test.jpg',
            'status'        => 'pending',
            'docling_json'  => ['texts' => [], 'tables' => []],
        ]);

        $extractionResult = [
            'subjects' => [
                'math'    => ['general mathematics' => 90.0],
                'science' => [],
                'english' => [],
                'others'  => [],
            ],
        ];

        $this->mock(DoclingParser::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn($extractionResult);

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertOk();
        $response->assertJsonStructure(['redirect']);
        $response->assertJsonMissing(['fallback']);

        expect(session('extraction_result'))->toBe($extractionResult);
    });

    // -----------------------------------------------------------------------
    // 4.3 — DoclingParser throws InvalidArgumentException → fallback + warning
    // -----------------------------------------------------------------------

    test('returns fallback and logs warning when DoclingParser throws InvalidArgumentException', function () {
        $user = User::factory()->create();

        UserFile::create([
            'user_id'       => $user->id,
            'type'          => 'report_card',
            'file_path'     => 'uploads/test.jpg',
            'original_name' => 'test.jpg',
            'status'        => 'pending',
            'docling_json'  => ['texts' => [], 'tables' => []],
        ]);

        Log::spy();

        $this->mock(DoclingParser::class)
            ->shouldReceive('extract')
            ->once()
            ->andThrow(new \InvalidArgumentException('No valid subject-grade pairs found.'));

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertOk();
        $response->assertJsonFragment(['fallback' => true]);
        $response->assertJsonStructure(['redirect', 'fallback', 'fallback_reason']);

        Log::shouldHaveReceived('warning')->once();
    });

    // -----------------------------------------------------------------------
    // 4.4 — DoclingParser throws RuntimeException → fallback + error log
    // -----------------------------------------------------------------------

    test('returns fallback and logs error when DoclingParser throws RuntimeException', function () {
        $user = User::factory()->create();

        UserFile::create([
            'user_id'       => $user->id,
            'type'          => 'report_card',
            'file_path'     => 'uploads/test.jpg',
            'original_name' => 'test.jpg',
            'status'        => 'pending',
            'docling_json'  => ['texts' => [], 'tables' => []],
        ]);

        Log::spy();

        $this->mock(DoclingParser::class)
            ->shouldReceive('extract')
            ->once()
            ->andThrow(new \RuntimeException('Failed to parse docling output.'));

        $response = $this->actingAs($user)->postJson('/api/grades/extract');

        $response->assertOk();
        $response->assertJsonFragment(['fallback' => true]);
        $response->assertJsonStructure(['redirect', 'fallback', 'fallback_reason']);

        Log::shouldHaveReceived('error')->once();
    });
});
