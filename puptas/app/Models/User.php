<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Role;
use App\Models\UserFile;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Grade;
use App\Models\ApplicantProfile;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Enums\RoleId;


class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    // NOTE: `role_id` is intentionally absent from $fillable. Roles are a
    // security boundary and must never be mass-assignable from a request.
    // Any code path that legitimately sets a role must use assignRole()
    // (forceFill), which is reserved for trusted server-side flows.
    protected $fillable = [
        'idp_user_id',
        'email',
        'password',
        'firstname',
        'middlename',
        'lastname',
        'salutation',
        'sex',
        'privacy_consent',
        'privacy_consent_at',
        'is_active',
        'anonymized_at',
    ];

    /**
     * Default new users to Applicant when no role is explicitly assigned.
     * This is a safety net for internal Eloquent creations (seeders, factories,
     * registration) that do not go through assignRole().
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if ($user->role_id === null) {
                $user->role_id = RoleId::Applicant->value;
            }
        });
    }

    /**
     * Assign a role in a way that bypasses mass-assignment protection.
     * Intended for trusted server-side flows only (e.g. superadmin user admin).
     *
     * Does not persist — call save() or set it before create().
     */
    public function assignRole(int $roleId): static
    {
        $this->forceFill(['role_id' => $roleId]);

        return $this;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function files()
    {
        return $this->hasMany(UserFile::class);
    }

    public function userFiles()
    {
        return $this->hasMany(UserFile::class);
    }

    /**
     * Get all applications for this user
     */
    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Legacy non-deterministic relationship - deprecated
     * Use currentApplication() or officiallyEnrolledApplication() instead
     */
    // public function application()
    // {
    //     return $this->hasOne(Application::class);
    // }

    /**
     * Get the latest (current) application for this user
     * This is the deterministic way to get a user's application
     */
    public function currentApplication()
    {
        return $this->hasOne(Application::class)
            ->select('applications.id', 'applications.user_id', 'applications.status', 'applications.submitted_at', 'applications.program_id', 'applications.second_choice_id', 'applications.third_choice_id', 'applications.enrollment_status', 'applications.enrollment_position', 'applications.requires_guidance_office', 'applications.requires_admission_office', 'applications.created_at', 'applications.updated_at', 'applications.deleted_at')
            ->whereNull('applications.deleted_at')
            ->ofMany('id', 'max');
    }

    /**
     * Get the latest officially enrolled application for this user
     * Returns null if user has no officially enrolled applications
     */
    public function officiallyEnrolledApplication()
    {
        return $this->hasOne(Application::class)
            ->select('applications.id', 'applications.user_id', 'applications.status', 'applications.submitted_at', 'applications.program_id', 'applications.second_choice_id', 'applications.third_choice_id', 'applications.enrollment_status', 'applications.enrollment_position', 'applications.requires_guidance_office', 'applications.requires_admission_office', 'applications.created_at', 'applications.updated_at', 'applications.deleted_at')
            ->where('applications.enrollment_status', 'officially_enrolled')
            ->whereNull('applications.deleted_at')
            ->ofMany('id', 'max');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(
            Program::class,
            'program_user',
            'user_id',
            'program_id'
        )
            ->withPivot('role_id')
            ->withTimestamps();
    }

    // User.php
    public function grades()
    {
        return $this->hasOne(Grade::class);
    }

    public function applicantProfile()
    {
        return $this->hasOne(ApplicantProfile::class);
    }

    public function getReferenceNumberAttribute()
    {
        return $this->testPasser?->reference_number;
    }

    public function testPasser()
    {
        return $this->hasOne(TestPasser::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'idp_user_id',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'privacy_consent_at' => 'datetime',
            'is_active' => 'boolean',
            'anonymized_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Scope query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Determine if the user is active.
     */
    public function isActive(): bool
    {
        return (bool) ($this->is_active ?? true) && is_null($this->deleted_at);
    }

    /**
     * Determine if the user is deactivated.
     */
    public function isDeactivated(): bool
    {
        return !$this->isActive();
    }

    /**
     * Permanently anonymize the user's personal identifying information (PII).
     * This operation is irreversible and audited.
     *
     * @return bool
     */
    public function anonymize(): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $oldValues = $this->only(['email', 'firstname', 'lastname', 'idp_user_id', 'is_active']);
            
            // Guaranteed unique email to prevent collision on users.email unique index
            $uniqueSuffix = (string) $this->id . '_' . \Illuminate\Support\Str::uuid()->toString();
            $anonymizedEmail = "anon_{$uniqueSuffix}@privacy.local";

            $this->forceFill([
                'email'                     => $anonymizedEmail,
                'firstname'                 => 'ANONYMIZED',
                'lastname'                  => 'USER_' . $this->id,
                'middlename'                => null,
                'salutation'                => null,
                'idp_user_id'               => null,
                'password'                  => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                'remember_token'            => null,
                'two_factor_secret'         => null,
                'two_factor_recovery_codes' => null,
                'is_active'                 => false,
                'anonymized_at'             => now(),
            ])->save();

            // Cascade to associated applicant profile
            if ($this->applicantProfile) {
                $this->applicantProfile->anonymize();
            }

            // Write immutable audit log
            app(\App\Services\AuditLogService::class)->logActivity(
                \App\Models\AuditLog::ACTION_UPDATE,
                'User Management',
                "User ID {$this->id} permanently anonymized (PII scrubbed).",
                $this,
                \App\Models\AuditLog::CATEGORY_USER_MANAGEMENT,
                $oldValues,
                [
                    'status'        => 'ANONYMIZED',
                    'anonymized_at' => $this->anonymized_at->toIso8601String(),
                ]
            );

            return true;
        });
    }

    /**
     * Determine if the user has been anonymized.
     */
    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }
}
