<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\UserFile;
use App\Models\User;
use App\Models\ApplicationProcess;
use Illuminate\Support\Facades\Cache;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'status',
        'submitted_at',
        'program_id',
        'second_choice_id',
        'third_choice_id',
        'enrollment_status',
        'enrollment_position',
        'requires_guidance_office',
        'requires_admission_office',
        'is_waivered',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'is_waivered' => 'boolean',
    ];

    /**
     * Cache key for the latest application of a user.
     */
    public static function cacheKeyForUser(string $userId): string
    {
        return "application:user:{$userId}";
    }

    /**
     * Invalidate cached applications whenever one changes.
     */
    protected static function booted(): void
    {
        static::saved(function (Application $application) {
            $userId = (string) $application->user_id;
            Cache::forget(static::cacheKeyForUser($userId));
            Cache::forget(static::cacheKeyForUser($userId) . ':returned_rejected');
        });

        static::deleted(function (Application $application) {
            $userId = (string) $application->user_id;
            Cache::forget(static::cacheKeyForUser($userId));
            Cache::forget(static::cacheKeyForUser($userId) . ':returned_rejected');
        });
    }

    public function user()
    {
        return $this->belongsTo(ApplicantProfile::class, 'user_id', 'user_id');
    }

    // app/Models/Application.php
    public function files()
    {
        return $this->hasMany(UserFile::class, 'user_id', 'user_id');
    }


    public function processes()
    {
        return $this->hasMany(ApplicationProcess::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function secondChoice()
    {
        return $this->belongsTo(Program::class, 'second_choice_id');
    }

    public function thirdChoice()
    {
        return $this->belongsTo(Program::class, 'third_choice_id');
    }
}
