<?php

namespace App\Repositories\Eloquent;

use App\Models\CutoffSettings;
use App\Repositories\Contracts\CutoffSettingsRepositoryInterface;

class EloquentCutoffSettingsRepository implements CutoffSettingsRepositoryInterface
{
    public function first(): ?CutoffSettings
    {
        return CutoffSettings::first();
    }

    public function create(array $data): CutoffSettings
    {
        return CutoffSettings::create($data);
    }
}