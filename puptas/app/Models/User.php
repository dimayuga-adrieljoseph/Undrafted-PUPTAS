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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'email',
        'password',
        'role_id',
        'firstname',
        'middlename',
        'lastname',
        'salutation',
        'birthday',
        'sex',
        'contactnumber',
        'address',
        'privacy_consent',
        'privacy_consent_at',
    ];

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
    public function application()
    {
        return $this->hasOne(Application::class);
    }

    /**
     * Get the latest (current) application for this user
     * This is the deterministic way to get a user's application
     */
    public function currentApplication()
    {
        return $this->hasOne(Application::class)
            ->select('applications.*')
            ->latestOfMany();
    }

    /**
     * Get the latest officially enrolled application for this user
     * Returns null if user has no officially enrolled applications
     */
    public function officiallyEnrolledApplication()
    {
        return $this->hasOne(Application::class)
            ->select('applications.*')
            ->where('enrollment_status', 'officially_enrolled')
            ->latestOfMany();
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
            'password' => 'hashed',
            'privacy_consent_at' => 'datetime',
        ];
    }
}
