<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SarGeneration extends Model
{
    protected $fillable = [
        'test_passer_id',
        'filename',
        'file_path',
        'enrollment_date',
        'enrollment_time',
        'sent_at',
        'sent_to_email',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * Get the test passer that owns the SAR generation
     */
    public function testPasser()
    {
        return $this->belongsTo(TestPasser::class, 'test_passer_id', 'test_passer_id');
    }
}
