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
        'notes',
        'performed_by',
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
