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
        'user_id',
        'status'
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(ApplicantProfile::class, 'user_id', 'user_id');
    }

    /**
     * Get the SAR generations for this test passer
     */
    public function sarGenerations()
    {
        return $this->hasMany(SarGeneration::class, 'test_passer_id', 'test_passer_id');
    }
}
