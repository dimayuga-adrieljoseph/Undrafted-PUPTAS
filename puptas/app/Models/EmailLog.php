<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'bulk_operation_id',
        'recipient_email',
        'recipient_name',
        'recipient_id',
        'email_type',
        'status',
        'error_message',
        'email_content',
        'retry_count',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'sent_at'   => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Get the bulk email operation this log belongs to.
     */
    public function bulkOperation()
    {
        return $this->belongsTo(BulkEmailOperation::class, 'bulk_operation_id');
    }
}
