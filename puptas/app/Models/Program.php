<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Application;

class Program extends Model
{
    
    protected $fillable = [
        'code', 'name', 'strand', 'math', 'science', 'english', 'gwa', 'pupcet', 'slots'
    ];

    public function applications()
{
    return $this->hasMany(Application::class);
}

public function users()
{
    return $this->belongsToMany(User::class, 'program_user', 'program_id', 'user_id')
                ->withPivot('role_id')
                ->withTimestamps();
}


}
