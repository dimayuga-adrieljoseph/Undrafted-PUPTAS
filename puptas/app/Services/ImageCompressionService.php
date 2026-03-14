<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Image Compression Service
 * 
 * Handles automatic image compression, resizing, and format conversion
 * for uploaded images in the PUPTAS application.
 */
class ImageCompressionService
{
    /**
     * Maximum width for resized images
     */
    private const MAX_WIDTH = 1200;

    /**
     * WebP compression quality (0-100)
     */
    private const QUALITY = 60;

    /**
     * Accepted MIME types for image uploads
     */
    private const ACCEPTED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/jpg',
        'image/webp',
        'image/gif'
    ];

    /**
     * Maximum file size in bytes (5MB)
     */
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /**
     * @var ImageManager
     */
    protected ImageManager $imageManager;

    /**
     * Create a new ImageCompressionService instance
     */
    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Process and compress an uploaded image
     * 
     * Pipeline: Upload → Validate → Resize → Compress → Convert to WebP → Save → Return Path
     *
     * @param UploadedFile $file The uploaded image file
     * @param string $directory The directory to store the compressed image (relative to public storage)
     * @return array{path: string, url: string, original_name: string, size: int}
     * @throws \InvalidArgumentException If the file is invalid or not an image
     * @throws \RuntimeException If image processing fails
     */
    public function compress(UploadedFile $file, string $directory = 'uploads'): array
    {
        // Validate file is valid
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Invalid file upload. The file failed to upload properly.');
        }

        // Get MIME type
        $mimeType = $file->getMimeType();
        
        // Validate MIME type
        if (empty($mimeType) || !in_array($mimeType, self::ACCEPTED_MIMES)) {
            throw new \InvalidArgumentException(
                'Invalid image format. Allowed formats: JPEG, PNG, WebP, GIF'
            );
        }

        // Validate file size (5MB max)
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                'File size exceeds maximum limit of 5MB'
            );
        }

        try {
            // Load the image
            $image = $this->imageManager->read($file->getRealPath());

            // Resize if wider than MAX_WIDTH (prevent upscaling of smaller images)
            if ($image->width() > self::MAX_WIDTH) {
                $image->resize(width: self::MAX_WIDTH, height: null);
            }

            // Convert to WebP format with compression
            $webpData = $image->toWebp(quality: self::QUALITY);

            // Generate unique filename to avoid collisions
            // Format: {slugified-original-name}_{timestamp}_{random-string}.webp
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $uniqueName = $this->generateUniqueFilename($originalName);

            // Full path within storage
            $fullPath = $directory . '/' . $uniqueName;

            // Ensure directory exists
            $this->ensureDirectoryExists($directory);

            // Store the compressed WebP image
            Storage::disk('public')->put($fullPath, $webpData);

            return [
                'path' => $fullPath,
                'url' => Storage::disk('public')->url($fullPath),
                'original_name' => $file->getClientOriginalName(),
                'size' => strlen($webpData),
            ];
        } catch (\Intervention\Image\Exception\NotReadableException $e) {
            throw new \InvalidArgumentException(
                'Unable to read the image file. The file may be corrupted or in an unsupported format.'
            );
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Failed to process image: ' . $e->getMessage()
            );
        }
    }

    /**
     * Validate image before processing
     *
     * @param UploadedFile $file
     * @return bool
     */
    public function validateImage(UploadedFile $file): bool
    {
        return $file->isValid()
            && in_array($file->getMimeType(), self::ACCEPTED_MIMES)
            && $file->getSize() <= self::MAX_FILE_SIZE;
    }

    /**
     * Generate a unique filename to avoid collisions
     *
     * @param string $originalName
     * @return string
     */
    private function generateUniqueFilename(string $originalName): string
    {
        $slug = Str::slug($originalName);
        $timestamp = time();
        $random = Str::random(8);
        
        return "{$slug}_{$timestamp}_{$random}.webp";
    }

    /**
     * Ensure the directory exists in storage
     *
     * @param string $directory
     * @return void
     */
    private function ensureDirectoryExists(string $directory): void
    {
        $disk = Storage::disk('public');
        
        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }
    }

    /**
     * Get the maximum allowed width for images
     *
     * @return int
     */
    public function getMaxWidth(): int
    {
        return self::MAX_WIDTH;
    }

    /**
     * Get the compression quality setting
     *
     * @return int
     */
    public function getQuality(): int
    {
        return self::QUALITY;
    }

    /**
     * Get the accepted MIME types
     *
     * @return array
     */
    public function getAcceptedMimes(): array
    {
        return self::ACCEPTED_MIMES;
    }

    /**
     * Get the maximum file size in bytes
     *
     * @return int
     */
    public function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }
}
