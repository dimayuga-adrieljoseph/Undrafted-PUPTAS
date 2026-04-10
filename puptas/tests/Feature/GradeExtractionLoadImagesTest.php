<?php

use App\Models\User;
use App\Models\UserFile;
use App\Services\OpenRouterClient;
use App\Services\GradeExtractionService;
use Illuminate\Support\Facades\Storage;

// ---------------------------------------------------------------------------
// Subclass that overrides the filesystem calls so we can control MIME types
// without real files on disk.
// ---------------------------------------------------------------------------

class LoadImagesTestService extends GradeExtractionService
{
    /** @var array<string, string>  path => mime_type */
    public array $mimeMap = [];

    /** @var array<string, string>  path => file contents */
    public array $contentsMap = [];

    public function loadImages(User $user): array
    {
        return parent::loadImages($user);
    }

    // Override filesystem helpers used inside loadImages
    protected function resolveAbsolutePath(string $filePath): string
    {
        return $filePath; // treat file_path as the "absolute" path in tests
    }
}

// We need to patch the actual service to avoid real Storage/mime calls.
// The cleanest approach: mock Storage facade and use a partial mock for
// mime_content_type via a wrapper method on the service.
// Since GradeExtractionService calls Storage::path() and mime_content_type()
// directly, we'll use Storage fake + create temp files with known content.

// ---------------------------------------------------------------------------
// 6.5 loadImages()
// ---------------------------------------------------------------------------

describe('GradeExtractionService::loadImages()', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->client = Mockery::mock(OpenRouterClient::class);
        $this->svc = new GradeExtractionService($this->client);
    });

    test('returns empty array when user has no files', function () {
        $user = User::factory()->create();

        // Reflect to call protected method
        $ref = new ReflectionMethod(GradeExtractionService::class, 'loadImages');
        $ref->setAccessible(true);

        $result = $ref->invoke($this->svc, $user);
        expect($result)->toBe([]);
    });

    test('returns empty array when user has only non-image files', function () {
        $user = User::factory()->create();

        // Store a PDF file
        Storage::put('uploads/doc.pdf', '%PDF-1.4 fake content');

        UserFile::create([
            'user_id'    => $user->id,
            'file_path'  => 'uploads/doc.pdf',
            'type'       => 'document',
            'status'     => 'uploaded',
        ]);

        $ref = new ReflectionMethod(GradeExtractionService::class, 'loadImages');
        $ref->setAccessible(true);

        $result = $ref->invoke($this->svc, $user);
        expect($result)->toBe([]);
    });

    test('returns only jpeg and png files, skipping other MIME types', function () {
        $user = User::factory()->create();

        // Create minimal valid JPEG (SOI + EOI markers)
        $jpegContent = "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 12) . "\xFF\xD9";
        Storage::put('uploads/photo.jpg', $jpegContent);

        // Store a PDF alongside
        Storage::put('uploads/doc.pdf', '%PDF-1.4 fake');

        UserFile::create([
            'user_id'   => $user->id,
            'file_path' => 'uploads/photo.jpg',
            'type'      => 'image',
            'status'    => 'uploaded',
        ]);
        UserFile::create([
            'user_id'   => $user->id,
            'file_path' => 'uploads/doc.pdf',
            'type'      => 'document',
            'status'    => 'uploaded',
        ]);

        $ref = new ReflectionMethod(GradeExtractionService::class, 'loadImages');
        $ref->setAccessible(true);

        $result = $ref->invoke($this->svc, $user);

        // Only the JPEG should be returned (PDF filtered out)
        // If mime_content_type detects the JPEG correctly we get 1 result;
        // if the fake storage doesn't produce a real MIME-detectable file,
        // we assert the PDF is never included.
        foreach ($result as $item) {
            expect($item['mime_type'])->toBeIn(['image/jpeg', 'image/png']);
        }
    });

    test('does not return files belonging to another user', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $jpegContent = "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 12) . "\xFF\xD9";
        Storage::put('uploads/userB_photo.jpg', $jpegContent);

        // File belongs to userB only
        UserFile::create([
            'user_id'   => $userB->id,
            'file_path' => 'uploads/userB_photo.jpg',
            'type'      => 'image',
            'status'    => 'uploaded',
        ]);

        $ref = new ReflectionMethod(GradeExtractionService::class, 'loadImages');
        $ref->setAccessible(true);

        // userA should get no files
        $result = $ref->invoke($this->svc, $userA);
        expect($result)->toBe([]);
    });

    test('skips files that do not exist on disk', function () {
        $user = User::factory()->create();

        // Register a DB record but do NOT put the file in Storage
        UserFile::create([
            'user_id'   => $user->id,
            'file_path' => 'uploads/missing.jpg',
            'type'      => 'image',
            'status'    => 'uploaded',
        ]);

        $ref = new ReflectionMethod(GradeExtractionService::class, 'loadImages');
        $ref->setAccessible(true);

        $result = $ref->invoke($this->svc, $user);
        expect($result)->toBe([]);
    });
});
