<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    // ─── Query scopes ────────────────────────────────────────────────────────

    /**
     * Scope: restrict to the latest application per user_id.
     *
     * Replaces the duplicated "WHERE id IN (SELECT MAX(id) FROM applications
     * WHERE deleted_at IS NULL GROUP BY user_id)" subquery that previously
     * appeared in four separate repository methods.
     *
     * Usage:
     *   Application::latestForUser()->where('enrollment_status', '...')
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeLatestForUser(Builder $query): Builder
    {
        // Note: the outer deleted_at filter is intentionally omitted here —
        // Application uses SoftDeletes, so the global scope already applies
        // whereNull('applications.deleted_at') to every Eloquent query.
        // Adding it again would cause column ambiguity errors on joins.
        // The subquery uses a raw DB builder (no global scope), so it needs
        // its own explicit whereNull.
        return $query
            ->whereIn('applications.id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('applications')
                    ->whereNull('deleted_at')
                    ->groupBy('user_id');
            });
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
