<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserFile;
use App\Models\User;
use App\Models\ApplicationProcess;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'submitted_at',
         'program_id',           // ← this must be here
    'second_choice_id',     // ← and this too
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // app/Models/Application.php
public function files()
{
    return $this->hasMany(UserFile::class, 'user_id', 'user_id');
}


    public function processes()
    {
        return $this->hasMany(ApplicationProcess::class);
    }

    public function program()
{
    return $this->belongsTo(Program::class);
}


    
}
