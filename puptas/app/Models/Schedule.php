<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'name',
        'start',
        'end',
        'type',
        'description',
        'location',
        'created_by',
        'affected_programs',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'affected_programs' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
