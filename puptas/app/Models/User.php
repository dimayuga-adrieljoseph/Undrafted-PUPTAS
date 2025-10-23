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

    protected $fillable = ['email', 'password', 'role_id','firstname',
        'middlename',
        'lastname',
        'birthday',
        'sex',
        'contactnumber',
        'address',];

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


    public function application()
{
    return $this->hasOne(Application::class);
}

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 
            'program_user', 'user_id', 'program_id')
                ->withPivot('role_id')
                ->withTimestamps();
    }

// User.php
public function grades()
{
    return $this->hasOne(Grade::class);
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
    protected $appends = [
        'profile_photo_url',
    ];

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
        ];
    }
}
