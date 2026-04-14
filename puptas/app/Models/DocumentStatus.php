<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentStatus extends Model
{
    protected $fillable = ['document_status'];

    public function applicantProfiles()
    {
        return $this->belongsToMany(ApplicantProfile::class, 'applicant_profile_document_status');
    }
}
