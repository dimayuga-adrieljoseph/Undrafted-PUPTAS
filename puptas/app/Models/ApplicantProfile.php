<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicantProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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

    public function firstChoiceProgram()
    {
        return $this->belongsTo(Program::class, 'first_choice_program');
    }

    public function secondChoiceProgram()
    {
        return $this->belongsTo(Program::class, 'second_choice_program');
    }
}
