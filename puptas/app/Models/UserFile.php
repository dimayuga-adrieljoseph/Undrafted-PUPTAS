<?php

namespace App\Models;

use App\Models\User;
use App\Models\Application;
use App\Models\ApplicationProcess;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'application_id',
        'application_process_id',
        'type',
        'file_path',
        'original_name',
        'status',
        'comment',
    ];

    protected $casts = [
        'uploadedFiles' => 'array',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function applicationProcess()
    {
        return $this->belongsTo(ApplicationProcess::class);
    }
}
