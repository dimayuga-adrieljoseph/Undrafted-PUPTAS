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
    ];

    protected $casts = [
        'date_graduated' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
