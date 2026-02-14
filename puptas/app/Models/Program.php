<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Application;

class Program extends Model
{

    protected $fillable = [
        'code',
        'name',
        'strand',
        'math',
        'science',
        'english',
        'gwa',
        'pupcet',
        'slots'
    ];

    protected $casts = [
        'math' => 'decimal:2',
        'science' => 'decimal:2',
        'english' => 'decimal:2',
        'gwa' => 'decimal:2',
        'pupcet' => 'decimal:2',
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
