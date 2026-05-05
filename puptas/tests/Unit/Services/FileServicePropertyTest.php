<?php

/**
 * Property-Based Test: FileService routes to the configured disk
 *
 * Property 1: For any valid UploadedFile and any FILESYSTEM_DISK value in
 * {public, s3}, calling FileService::store() should persist the file to the
 * disk matching the configured value, and the returned path should begin with
 * `uploads/files/`.
 *
 * Validates: Requirements 1.5, 2.4, 2.5
 *
 * Eris is not installed; property-based behaviour is simulated via a loop of
 * configurable iterations per disk with randomly-generated filenames.
 * Default: 20 iterations (configurable via PROPERTY_TEST_ITERATIONS env var)
 */

use App\Helpers\FileMapper;
use App\Services\FileService;
use App\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Return a random alphanumeric string of the given length.
 */
function randomFilename(int $length = 8): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $result . '.jpg';
}

// ---------------------------------------------------------------------------
// Property 1 — FileService routes to the configured disk
// Validates: Requirements 1.5, 2.4, 2.5
// ---------------------------------------------------------------------------

describe('Property 1: FileService routes to the configured disk', function () {

    /**
     * Run configurable iterations for the `public` disk.
     *
     * For each iteration a fresh fake UploadedFile with a random filename is
     * created, FileService::store() is called, and we assert:
     *   (a) the returned path starts with `uploads/files/`
     *   (b) the file exists on the `public` disk
     */
    test('routes to the public disk for random files', function () {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);

        $service = new FileService(
            new ImageCompressionService(),
            new FileMapper(),
        );

        $iterations = propertyTestIterations();

        for ($i = 0; $i < $iterations; $i++) {
            $filename = randomFilename();
            $file = UploadedFile::fake()->image($filename, random_int(10, 200), random_int(10, 200));

            $result = $service->store($file, 'uploads/files');

            // (a) returned path must start with uploads/files/
            expect($result['path'])->toStartWith('uploads/files/');

            // (b) file must exist on the public disk
            Storage::disk('public')->assertExists($result['path']);
        }
    });

    /**
     * Run configurable iterations for the `s3` disk.
     *
     * Same assertions as above but against the faked `s3` disk.
     */
    test('routes to the s3 disk for random files', function () {
        Storage::fake('s3');
        config(['filesystems.default' => 's3']);

        $service = new FileService(
            new ImageCompressionService(),
            new FileMapper(),
        );

        $iterations = propertyTestIterations();

        for ($i = 0; $i < $iterations; $i++) {
            $filename = randomFilename();
            $file = UploadedFile::fake()->image($filename, random_int(10, 200), random_int(10, 200));

            $result = $service->store($file, 'uploads/files');

            // (a) returned path must start with uploads/files/
            expect($result['path'])->toStartWith('uploads/files/');

            // (b) file must exist on the s3 disk
            Storage::disk('s3')->assertExists($result['path']);
        }
    });

    /**
     * Parametric check: for each disk value in {public, s3} run 5 iterations
     * with distinct random filenames and assert both invariants hold.
     *
     * This mirrors the task description's "at least 5 iterations per disk"
     * requirement and makes the disk-switching behaviour explicit.
     */
    test('stores to the correct disk for each configured value', function (string $disk) {
        Storage::fake($disk);
        config(['filesystems.default' => $disk]);

        $service = new FileService(
            new ImageCompressionService(),
            new FileMapper(),
        );

        for ($i = 0; $i < 5; $i++) {
            $filename = randomFilename();
            $file = UploadedFile::fake()->image($filename, 50, 50);

            $result = $service->store($file, 'uploads/files');

            expect($result['path'])->toStartWith('uploads/files/');
            Storage::disk($disk)->assertExists($result['path']);
        }
    })->with(['public', 's3']);
});
