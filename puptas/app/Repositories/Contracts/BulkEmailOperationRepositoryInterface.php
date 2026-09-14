<?php

namespace App\Repositories\Contracts;

use App\Models\BulkEmailOperation;

interface BulkEmailOperationRepositoryInterface
{
    /**
     * Create a bulk email operation record.
     */
    public function create(array $data): BulkEmailOperation;

    /**
     * Find a bulk email operation by ID or fail.
     */
    public function find(int $id): BulkEmailOperation;

    /**
     * Update bulk operations by IDs.
     */
    public function updateWhereIn(array $ids, array $values): int;

    /**
     * Update a single bulk operation by ID.
     */
    public function update(int $id, array $values): int;
}