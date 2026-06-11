<?php

namespace App\Helpers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use App\Models\UserFile;

/**
 * FileMapper - Centralized file mapping and formatting
 * 
 * Defines consistent mapping between:
 * - Form field names (from validation)
 * - Database type keys (stored in files collection)
 * - API response keys (returned to frontend)
 */
class FileMapper
{
    /**
     * Define all file mappings
     * Format: 'apiKey' => 'databaseType'
     */
    public const MAPPING = [
        // Grade cards (image-only — compressed + OCR)
        'file10Front' => 'file10_front',
        'file10'      => 'file10_back',
        'file11Front' => 'file11_front',
        'file11'      => 'file11_back',
        'file12Front' => 'file12_front',
        'file12'      => 'file12_back',

        // Official documents (images or PDF accepted)
        'fileId'        => 'school_id',
        'fileNonEnroll' => 'non_enroll_cert',
        'filePSA'       => 'psa',
        'fileGoodMoral' => 'good_moral',
        'fileUnderOath' => 'under_oath',
        'filePhoto2x2'  => 'photo_2x2',
    ];

    /**
     * Required file keys per graduate type.
     * Keys must match MAPPING keys above.
     */
    public const REQUIRED_BY_GRADUATE_TYPE = [
        'Senior High School of A.Y. 2025-2026' => [
            'file10Front', 'file10',
            'file11Front', 'file11',
            'file12Front', 'file12',
        ],
        'Senior High School of Past School Years' => [
            'file10Front', 'file10',
            'file11Front', 'file11',
            'file12Front', 'file12',
        ],
        'Alternative Learning System' => [
            'file10Front', 'file10',
            'file11Front', 'file11',
            'file12Front', 'file12',
        ],
    ];

    /**
     * Format only the required files for a given graduate type.
     * Returns null slots for missing files so the frontend knows what's required.
     *
     * @param Collection $files Files collection keyBy('type')
     * @param string|null $graduateType
     * @param bool $includeStatus
     * @return array
     */
    public static function formatFilesForGraduateType(Collection $files, ?string $graduateType, bool $includeStatus = true): array
    {
        $requiredKeys = self::REQUIRED_BY_GRADUATE_TYPE[$graduateType] ?? null;

        // Unknown/unsupported graduate type — return ALL uploaded files instead of empty array
        if ($requiredKeys === null) {
            \Log::warning('Unknown graduate type, returning all files', [
                'graduateType' => $graduateType,
                'fileCount' => $files->count(),
            ]);
            
            // Return all files that exist, using all possible keys from MAPPING
            $uploadedFiles = [];
            foreach (self::MAPPING as $apiKey => $databaseType) {
                if (isset($files[$databaseType])) {
                    $uploadedFiles[$apiKey] = self::buildFilePayload($files[$databaseType], $includeStatus);
                }
            }
            return $uploadedFiles;
        }

        $uploadedFiles = [];
        foreach ($requiredKeys as $apiKey) {
            $databaseType = self::MAPPING[$apiKey];
            $uploadedFiles[$apiKey] = isset($files[$databaseType])
                ? self::buildFilePayload($files[$databaseType], $includeStatus)
                : null;
        }

        return $uploadedFiles;
    }

    /**
     * Format only the required files for a given graduate type with minimal metadata.
     * OPTIMIZED: Returns only file metadata without URLs for lazy loading.
     * 
     * @param Collection $files Files collection keyBy('type')
     * @param string|null $graduateType
     * @return array
     */
    public static function formatFilesForGraduateTypeMinimal(Collection $files, ?string $graduateType): array
    {
        $requiredKeys = self::REQUIRED_BY_GRADUATE_TYPE[$graduateType] ?? null;

        // Unknown/unsupported graduate type — no documents are required yet, return empty array.
        if ($requiredKeys === null) {
            return [];
        }

        $uploadedFiles = [];
        foreach ($requiredKeys as $apiKey) {
            $databaseType = self::MAPPING[$apiKey];
            if (isset($files[$databaseType])) {
                $file = $files[$databaseType];
                $uploadedFiles[$apiKey] = [
                    'status' => $file->status ?? 'pending',
                    'comment' => $file->comment,
                    'originalName' => self::sanitizeFilename($file->original_name),
                    'hasFile' => true,
                    // URL will be loaded lazily by frontend
                ];
            } else {
                $uploadedFiles[$apiKey] = [
                    'status' => 'not_uploaded',
                    'hasFile' => false,
                ];
            }
        }

        return $uploadedFiles;
    }

    /**
     * Get all valid file field keys for validation
     * Used in ConfirmationController validation rules
     */
    public static function getValidFileFields(): string
    {
        return implode(',', array_keys(self::MAPPING));
    }

    /**
     * Format files collection for API response
     * Converts database types to API response structure
     * 
     * @param Collection $files Files collection keyBy('type')
     * @param bool $includeStatus Whether to include approval status
     * @return array
     */
    public static function formatFiles(Collection $files, bool $includeStatus = true): array
    {
        $uploadedFiles = [];

        foreach (self::MAPPING as $apiKey => $databaseType) {
            if (isset($files[$databaseType])) {
                $file = $files[$databaseType];
                $uploadedFiles[$apiKey] = self::buildFilePayload($file, $includeStatus);
            } else {
                $uploadedFiles[$apiKey] = null;
            }
        }

        return $uploadedFiles;
    }

    /**
     * Format files without status (for dashboard views)
     * Returns just the URL
     * 
     * @param Collection $files Files collection keyBy('type')
     * @return array
     */
    public static function formatFilesUrls(Collection $files): array
    {
        $uploadedFiles = [];

        foreach (self::MAPPING as $apiKey => $databaseType) {
            $uploadedFiles[$apiKey] = isset($files[$databaseType])
                ? self::buildFilePayload($files[$databaseType])
                : null;
        }

        return $uploadedFiles;
    }

    public static function buildFilePayload(UserFile $file, bool $includeStatus = false): array
    {
        // Fast path: Use extension-based mime type detection only
        $mimeType = self::guessMimeTypeFromPath($file->file_path);
        
        $payload = [
            'url' => self::buildPreviewUrl($file),
            'mimeType' => $mimeType,
            'originalName' => self::sanitizeFilename($file->original_name),
            'isImage' => str_starts_with($mimeType, 'image/'),
        ];

        if ($includeStatus) {
            $payload['status'] = $file->status;
        }

        return $payload;
    }

    /**
     * Build a signed preview URL so file access is authenticated and time-bound.
     */
    public static function buildPreviewUrl(UserFile $file): string
    {
        return URL::temporarySignedRoute(
            'files.preview',
            now()->addMinutes(60),
            ['file' => $file->id]
        );
    }

    public static function detectMimeType(UserFile $file): string
    {
        // Fast path: check extension first — all uploads are converted to .webp
        // by ImageCompressionService, so this avoids unnecessary disk I/O in most cases.
        $extensionMime = self::guessMimeTypeFromPath($file->file_path);
        if ($extensionMime !== 'application/octet-stream') {
            return $extensionMime;
        }

        // Slow path: read the actual file magic bytes for unknown extensions.
        // Works for both local and remote disks (e.g. S3) by reading raw bytes.
        $diskName = self::resolveDiskForPath($file->file_path);

        try {
            $storage = Storage::disk($diskName);

            if (in_array($diskName, ['public', 'local'], true)) {
                // Local disk: use the absolute path directly — no download needed.
                $absolutePath = $storage->path($file->file_path);
                if (is_file($absolutePath)) {
                    $mimeType = mime_content_type($absolutePath);
                    if (is_string($mimeType) && $mimeType !== '') {
                        return $mimeType;
                    }
                }
            } else {
                // Remote disk (e.g. S3): stream bytes and detect via finfo.
                $contents = $storage->get($file->file_path);
                if (is_string($contents) && $contents !== '') {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $detected = $finfo->buffer($contents);
                    if (is_string($detected) && $detected !== '') {
                        return $detected;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall back to extension-based guess.
        }

        return $extensionMime;
    }

    public static function resolveDiskForPath(string $path, bool &$found = false): string
    {
        $cacheKey = 'file_disk:' . md5($path);

        // Fast path: Check if we already resolved this file's disk recently.
        // Use the default cache store (file/array/redis) — never hardcode 'redis'
        // so this works in local environments without Redis installed.
        try {
            $cachedDisk = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($cachedDisk) {
                $found = true;
                return $cachedDisk;
            }
        } catch (\Throwable $e) {
            // Cache unavailable — continue without it
        }

        $configuredDefault = config('filesystems.default', 'public');
        $candidateDisks = [$configuredDefault];
        
        if ($configuredDefault !== 'public') $candidateDisks[] = 'public';
        if ($configuredDefault !== 'local') $candidateDisks[] = 'local';
        
        // Only check S3 as a fallback if not in local environment,
        // to prevent massive timeouts when factories generate fake file paths
        // that don't actually exist on disk.
        if (!app()->environment('local') && $configuredDefault !== 's3') {
            $candidateDisks[] = 's3';
        }

        $candidateDisks = array_unique($candidateDisks);

        foreach ($candidateDisks as $diskName) {
            try {
                if (Storage::disk($diskName)->exists($path)) {
                    $found = true;
                    // Cache the result for 30 days to bypass slow S3 exists() checks
                    try {
                        \Illuminate\Support\Facades\Cache::put($cacheKey, $diskName, now()->addDays(30));
                    } catch (\Throwable $e) {
                        // Cache write failed — non-fatal, continue
                    }
                    return $diskName;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        $found = false;
        return 'public';
    }

    public static function sanitizeFilename(?string $filename): string
    {
        $sanitized = trim((string) preg_replace('/[^A-Za-z0-9._ -]/', '', (string) $filename));

        return $sanitized !== '' ? $sanitized : 'document';
    }

    public static function guessMimeTypeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            'bmp'         => 'image/bmp',
            'pdf'         => 'application/pdf',
            default       => 'application/octet-stream',
        };
    }
}
