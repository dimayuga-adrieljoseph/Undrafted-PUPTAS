<?php

namespace App\Repositories\Eloquent;

use App\Models\SystemSetting;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;

class EloquentSystemSettingRepository implements SystemSettingRepositoryInterface
{
    public function firstByKey(string $key): ?SystemSetting
    {
        return SystemSetting::where('key', $key)->first();
    }

    public function updateOrCreate(array $attributes, array $values): SystemSetting
    {
        return SystemSetting::updateOrCreate($attributes, $values);
    }
}