<?php

namespace App\Services;

use App\Helpers\FileMapper;
use App\Models\UserFile;
use App\Services\DoclingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileService
{
    public function __construct(
        protected ImageCompressionService $compressionService,
        protected FileMapper $fileMapper,
        protected DoclingService $doclingService,
    ) {}

    /**
     * Compress and store an uploaded file on the active disk.
     *
     * @return array{path: string, original_name: string, docling_json: array|null}
     * @throws \RuntimeException If the storage put() fails
     */
    public function store(UploadedFile $file, string $directory): array
    {
        $result = $this->compressionService->processImage($file);

        // Build the full relative path
        $path = $directory . '/' . $result['filename'];

        // Requirement 8.4 — strip any leading slash or protocol prefix
        $path = $this->sanitizePath($path);

        $disk = $this->activeDisk();

        try {
            $stored = Storage::disk($disk)->put($path, $result['webp_data']);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'S3 put() failed for path "' . $path . '": ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($stored === false) {
            throw new \RuntimeException(
                'Storage put() returned false for path "' . $path . '" on disk "' . $disk . '".'
            );
        }

        // Send the WebP to Docling for structured JSON extraction (non-fatal).
        $doclingJson = $this->doclingService->convertToJson($result['webp_data'], $result['filename']);

        return [
            'path'          => $path,
            'original_name' => $result['original_name'],
            'docling_json'  => $doclingJson,
        ];
    }

    /**
     * Delete a file from whichever disk it resides on.
     * Non-fatal on S3: logs a warning instead of throwing.
     */
    public function delete(string $path): void
    {
        $disk = FileMapper::resolveDiskForPath($path);

        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable $e) {
            if ($disk === 's3') {
                Log::warning('FileService: S3 delete() failed.', [
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
                return;
            }

            throw $e;
        }
    }

    /**
     * Return a Signed_Preview_URL for the given UserFile.
     */
    public function url(UserFile $file): string
    {
        return FileMapper::buildPreviewUrl($file);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function activeDisk(): string
    {
        return config('filesystems.default', 'public');
    }

    /**
     * Strip any leading slash or protocol prefix from a path (Requirement 8.4).
     */
    private function sanitizePath(string $path): string
    {
        // Remove protocol prefix (e.g. "https://...", "s3://...")
        $path = (string) preg_replace('#^[a-zA-Z][a-zA-Z0-9+\-.]*://#', '', $path);

        // Remove leading slash(es)
        $path = ltrim($path, '/');

        return $path;
    }
}
