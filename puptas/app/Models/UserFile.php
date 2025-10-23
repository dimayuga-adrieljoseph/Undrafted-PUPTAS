<?php

namespace App\Models;
use App\Models\User;
use App\Models\Application;
use App\Models\ApplicationProcess;


use Illuminate\Database\Eloquent\Model;

class UserFile extends Model
{
    protected $fillable = ['user_id', 'type', 'file_path', 'original_name','created_at','updated_at', 'status', 'comment'];

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

}
