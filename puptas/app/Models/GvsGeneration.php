<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Grade Verification Slip Generation record.
 *
 * One row per applicant. Updated (download_count++) on every re-download.
 * The stored PDF is regenerated fresh on each download; the file_path
 * always points to the most recently generated copy.
 */
class GvsGeneration extends Model
{
    protected $table = 'gvs_generations';

    protected $fillable = [
        'user_id',
        'reference_number',
        'filename',
        'file_path',
        'download_count',
        'last_downloaded_at',
    ];

    protected $casts = [
        'last_downloaded_at' => 'datetime',
        'download_count'     => 'integer',
    ];

    /**
     * The applicant who owns this record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The applicant profile (for name/strand info).
     */
    public function applicantProfile()
    {
        return $this->hasOneThrough(
            ApplicantProfile::class,
            User::class,
            'id',        // users.id
            'user_id',   // applicant_profiles.user_id
            'user_id',   // gvs_generations.user_id
            'id'         // users.id
        );
    }
}
