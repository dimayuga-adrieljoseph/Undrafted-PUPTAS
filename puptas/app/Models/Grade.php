<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'english',
        'mathematics',
        'science',
        'g12_first_sem',
        'g12_second_sem',
    ];

    protected $casts = [
        'english' => 'decimal:2',
        'mathematics' => 'decimal:2',
        'science' => 'decimal:2',
        'g12_first_sem' => 'decimal:2',
        'g12_second_sem' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
