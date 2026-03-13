<?php

namespace App\Helpers;

use Illuminate\Support\Facades\URL;
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
        'file11' => 'file11_back',
        'file12' => 'file12_back',
        'schoolId' => 'school_id',
        'nonEnrollCert' => 'non_enroll_cert',
        'psa' => 'psa',
        'goodMoral' => 'good_moral',
        'underOath' => 'under_oath',
        'photo2x2' => 'photo_2x2',
    ];

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
                $uploadedFiles[$apiKey] = [
                    'url' => self::buildPreviewUrl($file),
                ];
                if ($includeStatus) {
                    $uploadedFiles[$apiKey]['status'] = $file->status;
                }
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
                ? self::buildPreviewUrl($files[$databaseType])
                : null;
        }

        return $uploadedFiles;
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
}
