<?php

namespace App\Repositories\Eloquent;

use App\Models\UserFile;
use App\Repositories\Contracts\UserFileRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentUserFileRepository implements UserFileRepositoryInterface
{
    public function firstByUserAndType(string $userId, string $type): ?UserFile
    {
        return UserFile::where('user_id', (string) $userId)
            ->where('type', $type)
            ->first();
    }

    public function updateOrCreate(array $attributes, array $values): UserFile
    {
        return UserFile::updateOrCreate($attributes, $values);
    }

    public function resetReturnedToPending(string $userId): int
    {
        return UserFile::where('user_id', (string) $userId)
            ->where('status', 'returned')
            ->update([
                'status' => 'pending',
                'comment' => null,
            ]);
    }

    public function allByUser(string $userId): Collection
    {
        return UserFile::where('user_id', (string) $userId)->get();
    }
}