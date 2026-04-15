<?php

use App\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

// ---------------------------------------------------------------------------
// ImageCompressionService::processImage()
// Validates: Requirements 3.1
// ---------------------------------------------------------------------------

describe('ImageCompressionService::processImage()', function () {

    beforeEach(function () {
        Storage::fake('public');
    });

    test('returns an array with the expected keys', function () {
        $service = new ImageCompressionService();
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $result = $service->processImage($file);

        expect($result)->toBeArray()
            ->toHaveKeys(['webp_data', 'original_name', 'filename']);
    });

    test('webp_data is a non-empty string', function () {
        $service = new ImageCompressionService();
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $result = $service->processImage($file);

        expect($result['webp_data'])->toBeString()->not->toBeEmpty();
    });

    test('filename ends with .webp', function () {
        $service = new ImageCompressionService();
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $result = $service->processImage($file);

        expect($result['filename'])->toEndWith('.webp');
    });

    test('original_name matches the uploaded file client original name', function () {
        $service = new ImageCompressionService();
        $file = UploadedFile::fake()->image('my-document.png', 50, 50);

        $result = $service->processImage($file);

        expect($result['original_name'])->toBe('my-document.png');
    });

    test('does not write any files to the public disk', function () {
        $service = new ImageCompressionService();
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $service->processImage($file);

        // Assert no files were written to the public disk
        Storage::disk('public')->assertDirectoryEmpty('');
    });
});
