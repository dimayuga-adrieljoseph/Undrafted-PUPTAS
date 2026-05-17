<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasserStatus extends Model
{
    use HasFactory;

    protected $table = 'passer_statuses';

    protected $fillable = [
        'status',
    ];
}