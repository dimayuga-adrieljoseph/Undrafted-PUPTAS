<?php

namespace App\Models;

use App\Models\UserFile;
use App\Models\Application;
use App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class ApplicationProcess extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'stage',
        'status',
        'action',
        'notes',
        'performed_by',
        'decision_reason',
        'reviewer_notes',
        'files_affected',
        'ip_address',
    ];

    protected $casts = [
        'files_affected' => 'array',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
