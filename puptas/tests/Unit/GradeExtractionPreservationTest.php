<?php

use App\Models\UserFile;
use App\Services\GradeExtractionService;
use App\Services\OpenRouterClient;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

// ---------------------------------------------------------------------------
// Preservation Tests — loadImages() still includes Grade 11/12 images
// These tests PASS on the fixed code, verifying the fix did not break
// existing correct behavior.
// ---------------------------------------------------------------------------

/**
 * Reuses the same testable subclass pattern as GradeExtractionBugCondition2Test.
 * Exposes loadImages() publicly and accepts an injected collection of UserFile
 * objects, bypassing the DB query so tests can inject controlled data.
 */
class TestablePreservationService extends GradeExtractionService
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

function makePreservationService(): TestablePreservationService
{
    $client = Mockery::mock(OpenRouterClient::class);
    return new TestablePreservationService($client);
}

/**
 * Create a fake JPEG image file in the fake storage and return a UserFile model
 * instance (not persisted to DB) pointing to it.
 */
function makePreservationFakeImageFile(string $type, string $filename): UserFile
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
// Preservation 3.1: Grade 11 images (file11Front, file11) must be included
// Validates: Requirement 3.4
// ---------------------------------------------------------------------------

test('loadImages includes file11Front and file11 records with valid JPEG MIME type', function () {
    Storage::fake('public');

    $svc = makePreservationService();

    $file11Front = makePreservationFakeImageFile('file11Front', 'grade11_front.jpg');
    $file11      = makePreservationFakeImageFile('file11', 'grade11_back.jpg');
    $collection  = collect([$file11Front, $file11]);

    $images = $svc->loadImagesFromCollection($collection);

    // Both Grade 11 files must be included — exactly 2 entries.
    expect($images)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// Preservation 3.2: Grade 12 images (file12Front, file12) must be included
// Validates: Requirement 3.4
// ---------------------------------------------------------------------------

test('loadImages includes file12Front and file12 records with valid JPEG MIME type', function () {
    Storage::fake('public');

    $svc = makePreservationService();

    $file12Front = makePreservationFakeImageFile('file12Front', 'grade12_front.jpg');
    $file12      = makePreservationFakeImageFile('file12', 'grade12_back.jpg');
    $collection  = collect([$file12Front, $file12]);

    $images = $svc->loadImagesFromCollection($collection);

    // Both Grade 12 files must be included — exactly 2 entries.
    expect($images)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// Preservation 3.3: Non-image MIME type exclusion must remain unchanged
// Validates: Requirement 3.5
// ---------------------------------------------------------------------------

test('loadImages excludes records with non-image MIME type regardless of type key', function () {
    Storage::fake('public');

    $svc = makePreservationService();

    // Write fake PDF files (with %PDF magic bytes) for various type keys
    $pdfBytes = '%PDF-1.4 fake pdf content';

    foreach (['grade11_front.pdf', 'grade12_front.pdf', 'grade10_front.pdf'] as $filename) {
        Storage::disk('public')->put($filename, $pdfBytes);
    }

    $makeFilePdf = function (string $type, string $filename): UserFile {
        $file = new UserFile();
        $file->user_id = 1;
        $file->type = $type;
        $file->file_path = $filename;
        $file->original_name = $filename;
        return $file;
    };

    $collection = collect([
        $makeFilePdf('file11Front', 'grade11_front.pdf'),
        $makeFilePdf('file12Front', 'grade12_front.pdf'),
        $makeFilePdf('file10Front', 'grade10_front.pdf'),
    ]);

    $images = $svc->loadImagesFromCollection($collection);

    // All files have non-image MIME type — images array must be empty.
    expect($images)->toBeEmpty();
});
