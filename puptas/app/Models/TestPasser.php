<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
        'previous_passer_status_id',
        'graduate_of',
        'graduation_date',
        'waiver_rank',
        'waiver_list_status',
        'waiver_program_offering',
    ];

    protected $casts = [
        'status'            => 'string',
        'pupcet_total_score' => 'float',
    ];

    public $timestamps = true;

    /**
     * Cache key for a user's test passer record.
     */
    public static function cacheKeyForUser(string $userId): string
    {
        return "test_passer:user:{$userId}";
    }

    /**
     * Invalidate cached test passer records whenever one changes.
     */
    protected static function booted(): void
    {
        static::saved(function (TestPasser $testPasser) {
            Cache::forget(static::cacheKeyForUser((string) $testPasser->user_id));
        });

        static::deleted(function (TestPasser $testPasser) {
            Cache::forget(static::cacheKeyForUser((string) $testPasser->user_id));
        });
    }

    public function user()
    {
        return $this->belongsTo(ApplicantProfile::class, 'user_id', 'user_id');
    }

    public function passerStatus()
    {
        return $this->belongsTo(PasserStatus::class);
    }

    public function previousPasserStatus()
    {
        return $this->belongsTo(PasserStatus::class, 'previous_passer_status_id');
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
