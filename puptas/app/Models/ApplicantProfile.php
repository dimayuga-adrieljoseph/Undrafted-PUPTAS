<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicantProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_number',
        'email',
        'salutation',
        'firstname',
        'middlename',
        'extension_name',
        'lastname',
        'birthday',
        'sex',
        'contactnumber',
        'street_address',
        'barangay',
        'city',
        'province',
        'postal_code',
        'privacy_consent',
        'privacy_consent_at',
        'school',
        'school_address',
        'school_year',
        'date_graduated',
        'strand',
        'track',
        'first_choice_program',
        'second_choice_program',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * These fields contain sensitive information and should not be exposed in API responses.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'school_address',
    ];

    protected $casts = [
        'date_graduated' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'user_id', 'user_id');
    }

    public function currentApplication()
    {
        return $this->hasOne(Application::class, 'user_id', 'user_id')
            ->select('applications.id', 'applications.user_id', 'applications.status', 'applications.submitted_at', 'applications.program_id', 'applications.second_choice_id', 'applications.enrollment_status', 'applications.enrollment_position', 'applications.created_at', 'applications.updated_at', 'applications.deleted_at')
            ->whereNull('applications.deleted_at')
            ->ofMany('id', 'max');
    }

    public function officiallyEnrolledApplication()
    {
        return $this->hasOne(Application::class, 'user_id', 'user_id')
            ->select('applications.id', 'applications.user_id', 'applications.status', 'applications.submitted_at', 'applications.program_id', 'applications.second_choice_id', 'applications.enrollment_status', 'applications.enrollment_position', 'applications.created_at', 'applications.updated_at', 'applications.deleted_at')
            ->where('applications.enrollment_status', 'officially_enrolled')
            ->whereNull('applications.deleted_at')
            ->ofMany('id', 'max');
    }

    public function grades()
    {
        return $this->hasOne(Grade::class, 'user_id', 'user_id');
    }

    public function testPasser()
    {
        return $this->hasOne(TestPasser::class, 'user_id', 'user_id');
    }

    public function firstChoiceProgram()
    {
        return $this->belongsTo(Program::class, 'first_choice_program');
    }

    public function secondChoiceProgram()
    {
        return $this->belongsTo(Program::class, 'second_choice_program');
    }
}
