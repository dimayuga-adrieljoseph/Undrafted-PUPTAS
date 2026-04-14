<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraduateType extends Model
{
    protected $fillable = ['label'];

    public function applicantProfiles()
    {
        return $this->belongsToMany(ApplicantProfile::class, 'applicant_profile_graduate_type');
    }
}
