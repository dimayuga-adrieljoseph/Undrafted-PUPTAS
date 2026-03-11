<?php

namespace App\Services;

use App\Models\Program;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Program Service
 * 
 * Handles business logic for program management.
 * Centralizes program-related operations including CRUD and audit logging.
 */
class ProgramService
{
    /**
     * Get all programs with their strands
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllProgramsWithStrands()
    {
        return Program::with('strands')->get();
    }

    /**
     * Get all programs (simple list)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllPrograms()
    {
        return Program::all();
    }

    /**
     * Create a new program with strands
     *
     * @param array $data
     * @param array $strandIds
     * @return Program
     */
    public function createProgram(array $data, array $strandIds = []): Program
    {
        return DB::transaction(function () use ($data, $strandIds) {
            $program = Program::create($data);

            if (!empty($strandIds)) {
                $program->strands()->attach($strandIds);
            }

            $program->load('strands');

            $this->logAudit(
                $program,
                'created',
                null,
                $program->toArray()
            );

            return $program;
        });
    }

    /**
     * Update an existing program with strands
     *
     * @param Program $program
     * @param array $data
     * @param array $strandIds
     * @return Program
     */
    public function updateProgram(Program $program, array $data, array $strandIds = []): Program
    {
        return DB::transaction(function () use ($program, $data, $strandIds) {
            $oldValues = $program->toArray();

            $program->update($data);

            $program->strands()->sync($strandIds);

            $program->load('strands');

            $this->logAudit(
                $program,
                'updated',
                $oldValues,
                $program->fresh()->toArray()
            );

            return $program;
        });
    }

    /**
     * Delete a program
     *
     * @param Program $program
     * @return void
     * @throws \Exception
     */
    public function deleteProgram(Program $program): void
    {
        DB::transaction(function () use ($program) {
            $oldValues = $program->toArray();

            $program->strands()->detach();

            $program->delete();

            $this->logAudit(
                $program,
                'deleted',
                $oldValues,
                null
            );
        });
    }

    /**
     * Find a program by ID or fail
     *
     * @param int $id
     * @return Program
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findProgramOrFail(int $id): Program
    {
        return Program::findOrFail($id);
    }

    /**
     * Log audit entry — delegates to AuditLogService (new schema).
     * Kept as a private helper so call sites don't need changing.
     */
    private function logAudit(Program $program, string $action, ?array $oldValues, ?array $newValues): void
    {
        // Map old free-form action strings to new ACTION_TYPE values
        $actionTypeMap = [
            'created' => 'CREATE',
            'updated' => 'UPDATE',
            'deleted' => 'DELETE',
        ];
        $actionType  = $actionTypeMap[strtolower($action)] ?? strtoupper($action);
        $description = ucfirst($action) . " program \"{$program->name}\" (ID: {$program->id}).";

        app(\App\Services\AuditLogService::class)->logActivity($actionType, 'Programs', $description, null, 'SYSTEM_OPERATION');
    }
}
