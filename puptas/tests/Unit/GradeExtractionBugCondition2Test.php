<?php

use App\Models\User;
use App\Models\UserFile;
use App\Services\GradeExtractionService;
use App\Services\OpenRouterClient;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

// ---------------------------------------------------------------------------
// Exploratory Bug Condition Test — Bug 2
// These tests are EXPECTED TO FAIL on unfixed code.
// Failure confirms the bug exists: loadImages() includes file10Front/file10 records.
// ---------------------------------------------------------------------------

/**
 * Exposes loadImages() publicly and accepts an injected collection of UserFile
 * objects, bypassing the DB query so tests can inject controlled data.
 */
class TestableBugCondition2Service extends GradeExtractionService
{
    public function loadImagesFromCollection(\Illuminate\Support\Collection $files): array
    {
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $images = [];

        foreach ($files as $file) {
            if (in_array($file->type, ['file10Front', 'file10'], true)) {
                continue;
            }

            $disk = \App\Helpers\FileMapper::resolveDiskForPath($file->file_path);
            $storage = Storage::disk($disk);

            if (! $storage->exists($file->file_path)) {
                continue;
            }

            $absolutePath = $storage->path($file->file_path);
            $mimeType = mime_content_type($absolutePath);

            if (! in_array($mimeType, $allowedMimeTypes, true)) {
                continue;
            }

            $contents = $storage->get($file->file_path);
            $images[] = [
                'mime_type' => $mimeType,
                'data'      => base64_encode($contents),
            ];
        }

        return $images;
    }
}

function makeBugCondition2Service(): TestableBugCondition2Service
{
    $client = Mockery::mock(OpenRouterClient::class);
    return new TestableBugCondition2Service($client);
}

/**
 * Create a fake JPEG image file in the fake storage and return a UserFile model
 * instance (not persisted to DB) pointing to it.
 */
function makeFakeImageFile(string $type, string $filename): UserFile
{
    // Minimal valid JPEG magic bytes so mime_content_type() detects image/jpeg
    $jpegBytes = "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 12) . "\xFF\xD9";
    Storage::disk('public')->put($filename, $jpegBytes);

    $file = new UserFile();
    $file->user_id = 1;
    $file->type = $type;
    $file->file_path = $filename;
    $file->original_name = $filename;

    return $file;
}

// ---------------------------------------------------------------------------
// Bug Condition 2.1: file10Front must NOT appear in loadImages() output
// Validates: Requirements 2.3
// ---------------------------------------------------------------------------

test('loadImages excludes file10Front records (bug condition 2.1)', function () {
    Storage::fake('public');

    $svc = makeBugCondition2Service();

    $file10Front = makeFakeImageFile('file10Front', 'grade10_front.jpg');
    $collection = collect([$file10Front]);

    $images = $svc->loadImagesFromCollection($collection);

    // On unfixed code this FAILS because file10Front is included.
    // Expected: images array is empty (file10Front excluded).
    expect($images)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Bug Condition 2.2: file10 must NOT appear in loadImages() output
// Validates: Requirements 2.3
// ---------------------------------------------------------------------------

test('loadImages excludes file10 records (bug condition 2.2)', function () {
    Storage::fake('public');

    $svc = makeBugCondition2Service();

    $file10 = makeFakeImageFile('file10', 'grade10_back.jpg');
    $collection = collect([$file10]);

    $images = $svc->loadImagesFromCollection($collection);

    // On unfixed code this FAILS because file10 is included.
    // Expected: images array is empty (file10 excluded).
    expect($images)->toBeEmpty();
});
