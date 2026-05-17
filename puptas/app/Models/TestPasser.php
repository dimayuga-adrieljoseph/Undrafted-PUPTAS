<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestPasser extends Model
{
    protected $primaryKey = 'test_passer_id';

    protected $fillable = [
        'surname',
        'first_name',
        'middle_name',
        'date_of_birth',
        'address',
        'school_address',
        'shs_school',
        'strand',
        'year_graduated',
        'email',
        'reference_number',
        'batch_number',
        'school_year',
        'pupcet_total_score',
        'user_id',
        'status'
    ];

    protected $casts = [
        'status'            => 'string',
        'pupcet_total_score' => 'float',
    ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(ApplicantProfile::class, 'user_id', 'user_id');
    }

    public function passerStatus()
    {
        return $this->belongsTo(PasserStatus::class);
    }

    /**
     * Get the SAR generations for this test passer
     */
    public function sarGenerations()
    {
        return $this->hasMany(SarGeneration::class, 'test_passer_id', 'test_passer_id');
    }
}
