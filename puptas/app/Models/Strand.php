<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Strand extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all programs associated with this strand
     * Many-to-many through program_strand junction table
     */
    public function programs()
    {
        return $this->belongsToMany(Program::class, 'program_strand', 'strand_id', 'program_id')
            ->withTimestamps();
    }

    /**
     * Scope: Get only active strands
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get strand by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }
}
