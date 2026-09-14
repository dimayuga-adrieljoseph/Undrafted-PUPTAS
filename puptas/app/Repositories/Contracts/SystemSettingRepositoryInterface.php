<?php

namespace App\Repositories\Contracts;

use App\Models\SystemSetting;

interface SystemSettingRepositoryInterface
{
    /**
     * First system setting for a key (or null).
     */
    public function firstByKey(string $key): ?SystemSetting;

    /**
     * Update-or-create a system setting by key.
     */
    public function updateOrCreate(array $attributes, array $values): SystemSetting;
}