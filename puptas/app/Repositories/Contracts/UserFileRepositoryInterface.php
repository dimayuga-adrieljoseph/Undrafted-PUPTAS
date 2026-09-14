<?php

namespace App\Repositories\Contracts;

use App\Models\UserFile;

interface UserFileRepositoryInterface
{
    /**
     * First file matching user + type.
     */
    public function firstByUserAndType(string $userId, string $type): ?UserFile;

    /**
     * Update-or-create a file by attributes.
     */
    public function updateOrCreate(array $attributes, array $values): UserFile;

    /**
     * Reset returned files to pending for a user.
     */
    public function resetReturnedToPending(string $userId): int;

    /**
     * All files for a user.
     */
    public function allByUser(string $userId);
}