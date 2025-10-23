<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestPasser extends Model
{
    protected $primaryKey = 'test_passer_id';

    protected $fillable = [
        'surname', 'first_name', 'middle_name', 'date_of_birth',
        'address', 'school_address', 'shs_school', 'strand',
        'year_graduated', 'email', 'reference_number',
        'batch_number', 'school_year'
    ];

    public $timestamps = true;

}
