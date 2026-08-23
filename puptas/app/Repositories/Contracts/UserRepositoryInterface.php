<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    /**
     * Staff users (role_id > 1) with programs and role, newest first.
     */
    public function staffWithProgramsAndRole(): Collection;

    /**
     * Staff users (role_id > 1) grouped by role with counts.
     *
     * @return array<int, int>
     */
    public function staffCountsByRole(): array;

    /**
     * Number of staff users (role_id > 1).
     */
    public function staffCount(): int;

    /**
     * Create a new staff user.
     */
    public function create(array $data): User;

    /**
     * Staff users with programs and role for search pagination.
     * When $offset and $limit are supplied, applies DB-level LIMIT/OFFSET.
     */
    public function searchStaff(?int $roleId, ?string $term, int $offset = 0, int $limit = PHP_INT_MAX): Collection;

    /**
     * Number of staff users matching the search.
     */
    public function countSearchStaff(?int $roleId, ?string $term): int;
}