<?php

namespace App\Repositories\Eloquent;

use App\Models\BulkEmailOperation;
use App\Repositories\Contracts\BulkEmailOperationRepositoryInterface;

class EloquentBulkEmailOperationRepository implements BulkEmailOperationRepositoryInterface
{
    public function create(array $data): BulkEmailOperation
    {
        return BulkEmailOperation::create($data);
    }

    public function find(int $id): BulkEmailOperation
    {
        return BulkEmailOperation::findOrFail($id);
    }

    public function updateWhereIn(array $ids, array $values): int
    {
        return BulkEmailOperation::whereIn('id', $ids)->update($values);
    }

    public function update(int $id, array $values): int
    {
        return BulkEmailOperation::where('id', $id)->update($values);
    }
}