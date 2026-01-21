<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramCapacityLog extends Model
{
    protected $fillable = [
        'program_id',
        'slots',
        'enrolled_count',
        'available_slots',
        'recorded_at',
    ];

    protected $casts = [
        'slots' => 'integer',
        'enrolled_count' => 'integer',
        'available_slots' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
