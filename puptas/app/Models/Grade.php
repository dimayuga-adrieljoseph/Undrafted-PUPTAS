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
        // G11 Math subjects
        'g11_general_mathematics',
        'g11_statistics_probability',
        // G11 English subjects
        'g11_oral_communication',
        'g11_21st_century_lit',
        'g11_academic_professional',
        'g11_reading_writing',
        // G11 Science subjects
        'g11_earth_life_science',
        'g11_physical_science',
        // ABM specific
        'g11_business_mathematics',
        'g12_21st_century_lit',
        // STEM specific - G11 Math
        'g11_pre_calculus',
        'g11_basic_calculus',
        // STEM specific - G11 Science
        'g11_earth_science',
        'g11_general_chemistry_1',
        // STEM specific - G12 Science
        'g12_general_physics_1',
        'g12_general_biology_1',
        'g12_general_physics_2',
        'g12_general_biology_2',
        'g12_general_chemistry_2',
        // STEM specific - G12 English
        'g12_academic_professional',
        // HUMSS specific - G12 Science
        'g12_earth_life_science',
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
        return $this->belongsTo(ApplicantProfile::class, 'user_id', 'user_id');
    }
}
