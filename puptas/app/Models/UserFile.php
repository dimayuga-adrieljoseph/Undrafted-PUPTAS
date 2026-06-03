<?php

namespace App\Models;

use App\Models\User;
use App\Models\Application;
use App\Models\ApplicationProcess;


use Illuminate\Database\Eloquent\Model;

class UserFile extends Model
{
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
