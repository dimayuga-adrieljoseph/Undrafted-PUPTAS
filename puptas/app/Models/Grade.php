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
        'g11_first_sem',
        'g11_second_sem',
        'g12_first_sem',
        'g12_second_sem',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
