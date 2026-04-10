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
        'file10Front' => 'file10_front',
        'file10' => 'file10_back',
        'file11Front' => 'file11_front',
        'file11' => 'file11_back',
        'file12Front' => 'file12_front',
        'file12' => 'file12_back',
        'nof137a' => 'f137a',
        'schoolId' => 'school_id',
        'nonEnrollCert' => 'non_enroll_cert',
        'psa' => 'psa',
        'goodMoral' => 'good_moral',
        'underOath' => 'under_oath',
        'photo2x2' => 'photo_2x2',
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
            'nof137a',
        ],
        'Alternative Learning System' => [
            'psa',
            'goodMoral',
            'underOath',
            'photo2x2',
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

        // Unknown/unsupported graduate type — no documents are required yet, return empty array.
        if ($requiredKeys === null) {
            return [];
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
        $mimeType = self::detectMimeType($file);
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

        // Slow path: read the actual file magic bytes for unknown extensions
        $diskName = self::resolveDiskForPath($file->file_path);

        try {
            if (in_array($diskName, ['public', 'local'], true)) {
                $absolutePath = Storage::disk($diskName)->path($file->file_path);

                if (is_file($absolutePath)) {
                    $mimeType = mime_content_type($absolutePath);
                    if (is_string($mimeType) && $mimeType !== '') {
                        return $mimeType;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall back to extension-based guess.
        }

        return $extensionMime;
    }

    public static function resolveDiskForPath(string $path): string
    {
        $configuredDefault = config('filesystems.default', 'public');
        $candidateDisks = array_unique([$configuredDefault, 'public', 'local', 's3']);

        foreach ($candidateDisks as $diskName) {
            try {
                if (Storage::disk($diskName)->exists($path)) {
                    return $diskName;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return 'public';
    }

    public static function sanitizeFilename(?string $filename): string
    {
        $sanitized = trim((string) preg_replace('/[^A-Za-z0-9._ -]/', '', (string) $filename));

        return $sanitized !== '' ? $sanitized : 'document';
    }

    private static function guessMimeTypeFromPath(string $path): string
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
