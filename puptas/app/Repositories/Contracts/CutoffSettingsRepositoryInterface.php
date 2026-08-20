<?php

namespace App\Repositories\Contracts;

use App\Models\CutoffSettings;

interface CutoffSettingsRepositoryInterface
{
    /**
     * First cutoff settings record (or null).
     */
    public function first(): ?CutoffSettings;

    /**
     * Create a cutoff settings record.
     */
    public function create(array $data): CutoffSettings;
}