<?php

namespace App\Models;

use App\Models\User;
use App\Models\Application;
use App\Models\ApplicationProcess;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class UserFile extends Model
{
    use SoftDeletes;
    /** Status constants — backend is authoritative for upload state */
    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'application_id',
        'application_process_id',
        'type',
        'file_path',
        'original_name',
        'status',
        'comment',
    ];

    protected $casts = [
        'uploadedFiles' => 'array',
    ];

    /**
     * Cache key for a single user+type file record.
     */
    public static function cacheKeyForUserAndType(string $userId, string $type): string
    {
        return "user_file:user:{$userId}:{$type}";
    }

    /**
     * Cache key for the full file list of a user.
     */
    public static function cacheKeyForUser(string $userId): string
    {
        return "user_file:user:{$userId}";
    }

    /**
     * Invalidate cached file records whenever a file changes.
     */
    protected static function booted(): void
    {
        static::saved(function (UserFile $file) {
            Cache::forget(static::cacheKeyForUser((string) $file->user_id));
            if ($file->type) {
                Cache::forget(static::cacheKeyForUserAndType((string) $file->user_id, $file->type));
            }
        });

        static::deleted(function (UserFile $file) {
            Cache::forget(static::cacheKeyForUser((string) $file->user_id));
            if ($file->type) {
                Cache::forget(static::cacheKeyForUserAndType((string) $file->user_id, $file->type));
            }
        });
    }

    /**
     * Check if the file is currently being uploaded (in-flight).
     */
    public function isUploading(): bool
    {
        return $this->status === self::STATUS_UPLOADING;
    }

    /**
     * Check if the file upload has completed (regardless of approval status).
     */
    public function isUploaded(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_RETURNED], true);
    }

    /**
     * Check if the file upload failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function user()
    {
        return $this->belongsTo(ApplicantProfile::class, 'user_id', 'user_id');
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function applicationProcess()
    {
        return $this->belongsTo(ApplicationProcess::class);
    }
}
