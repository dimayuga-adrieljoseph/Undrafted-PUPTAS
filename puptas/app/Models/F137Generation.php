<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * F137 Request Letter Generation record.
 *
 * One row per applicant. Updated (download_count++) on every re-download.
 * The PDF is regenerated fresh on each download with the current Philippine date.
 */
class F137Generation extends Model
{
    protected $table = 'f137_generations';

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
}
