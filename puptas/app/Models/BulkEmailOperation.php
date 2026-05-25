<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BulkEmailOperation extends Model
{
    protected $fillable = [
        'email_type',
        'status',
        'total_count',
        'sent_count',
        'failed_count',
        'pending_count',
        'initiated_by',
        'batch_number',
        'school_year',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the email logs for this bulk operation.
     */
    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class, 'bulk_operation_id');
    }

    /**
     * Get the user who initiated this bulk operation.
     */
    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
