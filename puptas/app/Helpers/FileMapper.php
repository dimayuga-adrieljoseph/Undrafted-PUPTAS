<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

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
                    'url' => Storage::url($file->file_path),
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
                ? Storage::url($files[$databaseType]->file_path)
                : null;
        }

        return $uploadedFiles;
    }
}
