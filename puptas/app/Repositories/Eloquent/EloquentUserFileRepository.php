<?php

namespace App\Repositories\Eloquent;

use App\Models\UserFile;
use App\Repositories\Contracts\UserFileRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EloquentUserFileRepository implements UserFileRepositoryInterface
{
    public function firstByUserAndType(string $userId, string $type): ?UserFile
    {
        $userId = (string) $userId;

        return Cache::remember(UserFile::cacheKeyForUserAndType($userId, $type), 3600, function () use ($userId, $type) {
            $file = UserFile::where('user_id', $userId)
                ->where('type', $type)
                ->first();

            return $file ?: false;
        }) ?: null;
    }

    public function updateOrCreate(array $attributes, array $values): UserFile
    {
        $file = UserFile::updateOrCreate($attributes, $values);

        $this->forgetUserCaches((string) $file->user_id);

        return $file;
    }

    public function resetReturnedToPending(string $userId): int
    {
        $userId = (string) $userId;

        $count = UserFile::where('user_id', $userId)
            ->where('status', 'returned')
            ->update([
                'status' => 'pending',
                'comment' => null,
            ]);

        if ($count > 0) {
            $this->forgetUserCaches($userId);
        }

        return $count;
    }

    public function allByUser(string $userId): Collection
    {
        $userId = (string) $userId;

        return Cache::remember(UserFile::cacheKeyForUser($userId), 3600, function () use ($userId) {
            return UserFile::where('user_id', $userId)->get();
        });
    }

    /**
     * Invalidate all cached records for a user's files.
     */
    private function forgetUserCaches(string $userId): void
    {
        Cache::forget(UserFile::cacheKeyForUser($userId));

        // A bulk update doesn't dispatch model events, so we can't know which
        // individual type keys to expire. Flush the per-type namespace via a
        // dedicated tag-free prefix scan is not possible with the file cache.
        // Instead, we iterate known file types to clear their single-record keys.
        foreach (array_keys(\App\Helpers\FileMapper::MAPPING ?? []) as $type) {
            Cache::forget(UserFile::cacheKeyForUserAndType($userId, $type));
        }
    }
}