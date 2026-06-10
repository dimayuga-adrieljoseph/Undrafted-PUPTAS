<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CutoffSettings extends Model
{
    /**
     * The table associated with the model.
     *
     * This model always operates on a singleton row (ID = 1).
     * Use CutoffSettingsService to read/write — do not query this model directly.
     *
     * @var string
     */
    protected $table = 'cutoff_settings';

    protected $fillable = ['cutoff_at'];

    protected $casts = [
        'cutoff_at' => 'immutable_datetime',
    ];
}
