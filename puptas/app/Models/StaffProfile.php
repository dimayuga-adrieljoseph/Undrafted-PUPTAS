<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'role_id',
        'role_name',
    ];

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'program_user', 'user_id', 'program_id');
    }
}
