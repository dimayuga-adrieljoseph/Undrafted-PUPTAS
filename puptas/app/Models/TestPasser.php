<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestPasser extends Model
{
    use HasFactory;
    
    protected $primaryKey = 'test_passer_id';

    protected $fillable = [
        'surname',
        'first_name',
        'middle_name',
        'strand',
        'shs_school',
        'year_graduated',
        'email',
        'reference_number',
        'batch_number',
        'school_year',
        'pupcet_total_score',
        'user_id',
        'status',
        'passer_status_id',
        'graduate_of',
        'graduation_date'
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

    /**
     * Get the graduation year for SAR form generation.
     * Priority: year_graduated field → date_graduated from profile → current year
     * 
     * @return string 4-digit year (e.g., "2024")
     */
    public function getGraduationYearAttribute(): string
    {
        // Priority 1: Use year_graduated if explicitly set
        if (!empty($this->attributes['year_graduated'])) {
            return (string) $this->attributes['year_graduated'];
        }

        // Priority 2: Extract year from applicant profile's date_graduated
        if ($this->user && $this->user->date_graduated) {
            return $this->user->date_graduated->format('Y');
        }

        // Priority 3: Fallback to current year
        return date('Y');
    }
}
