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
        'created_by_user_id',
        'email_sent_successfully',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'email_sent_successfully' => 'boolean',
    ];

    /**
     * Get the test passer that owns the SAR generation
     */
    public function testPasser()
    {
        return $this->belongsTo(TestPasser::class, 'test_passer_id', 'test_passer_id');
    }

    /**
     * Get the user who created/sent this SAR
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }
}
