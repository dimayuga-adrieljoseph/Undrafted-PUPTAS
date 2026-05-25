<?php

namespace App\Services;

use App\Helpers\FileMapper;
use App\Models\UserFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    public function __construct(
        protected ImageCompressionService $compressionService,
        protected FileMapper $fileMapper,
    ) {}

    /**
    * Store the uploaded file without any image processing.
    * Used for non-image documents (PDFs) and cases where client-side compression
    * already applied. Returns the same shape as the `store` method.
    */
    public function storeRaw(UploadedFile $file, string $directory): array
    {
        $disk = $this->activeDisk();

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $this->sanitizePath($directory . '/' . $filename);

        try {
            // Use putFileAs for streaming upload (avoids loading entire file into memory)
            // This also enables automatic multipart upload for large files on S3
            $stored = Storage::disk($disk)->putFileAs(
                dirname($path),
                $file,
                basename($path)
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Storage put() failed for path "' . $path . '": ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($stored === false) {
            throw new \RuntimeException(
                'Storage put() returned false for path "' . $path . '" on disk "' . $disk . '".'
            );
        }

        return [
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Confirm a direct-to-S3 upload by recording the file path in the database.
     * Used with presigned URL uploads where the file is already on S3.
     */
    public function confirmDirectUpload(string $path, string $originalName): array
    {
        return [
            'path'          => $this->sanitizePath($path),
            'original_name' => $originalName,
        ];
    }

    /**
     * Generate a presigned upload URL for direct-to-S3 uploads.
     * Returns the URL, headers, and the storage path.
     */
    public function generateUploadUrl(string $directory, string $extension): array
    {
        $disk = $this->activeDisk();
        $filename = Str::uuid() . '.' . $extension;
        $path = $this->sanitizePath($directory . '/' . $filename);

        // For S3-compatible disks, generate a presigned PUT URL
        if (in_array($disk, ['s3', 'sar_tmp'])) {
            $client = Storage::disk($disk)->getClient();
            $bucket = config("filesystems.disks.{$disk}.bucket");

            $command = $client->getCommand('PutObject', [
                'Bucket' => $bucket,
                'Key'    => $path,
            ]);

            $presignedUrl = (string) $client->createPresignedRequest($command, '+30 minutes')->getUri();

            return [
                'url'  => $presignedUrl,
                'path' => $path,
                'disk' => $disk,
            ];
        }

        // Fallback: for local disks, return null URL (frontend will use traditional upload)
        return [
            'url'  => null,
            'path' => $path,
            'disk' => $disk,
        ];
    }

    /**
     * Compress and store an uploaded file on the active disk.
     *
     * Note: this method stores a compressed image and returns storage path
     * and original filename. It does not populate OCR data; callers should
     * dispatch OCR jobs (see ProcessGradeOcr) when needed.
     *
     * @return array{path: string, original_name: string}
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

        return [
            'path'          => $path,
            'original_name' => $result['original_name'],
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
