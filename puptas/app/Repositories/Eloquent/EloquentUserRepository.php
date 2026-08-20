<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function staffWithProgramsAndRole(): Collection
    {
        return User::with(['programs:id,name,code', 'role'])
            ->where('role_id', '>', 1)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function staffCountsByRole(): array
    {
        return User::where('role_id', '>', 1)
            ->select('role_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->toArray();
    }

    public function staffCount(): int
    {
        return User::where('role_id', '>', 1)->count();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function searchStaff(?int $roleId, ?string $term): Collection
    {
        $query = User::with(['programs:id,name,code', 'role'])
            ->where('role_id', '>', 1);

        if ($roleId && $roleId > 1) {
            $query->where('role_id', $roleId);
        }

        $this->applyNameEmailTerm($query, $term);

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function countSearchStaff(?int $roleId, ?string $term): int
    {
        $query = User::where('role_id', '>', 1);

        if ($roleId && $roleId > 1) {
            $query->where('role_id', $roleId);
        }

        $this->applyNameEmailTerm($query, $term);

        return $query->count();
    }

    private function applyNameEmailTerm($query, ?string $term): void
    {
        if (!$term) {
            return;
        }

        $like = '%' . $term . '%';
        $query->where(function ($q) use ($like) {
            $q->where('firstname', 'like', $like)
              ->orWhere('lastname', 'like', $like)
              ->orWhere('email', 'like', $like);
        });
    }
}