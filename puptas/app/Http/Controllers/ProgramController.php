<?php

namespace App\Http\Controllers;

use App\Models\Strand;
use App\Services\ProgramService;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;

class ProgramController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param ProgramService $programService
     */
    public function __construct(
        private ProgramService $programService
    ) {}

    /**
     * Fetch all programs with strands
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $programs = $this->programService->getAllProgramsWithStrands();
        return response()->json($programs);
    }

    /**
     * Create a new program
     *
     * @param StoreProgramRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreProgramRequest $request)
    {
        $validated = $request->validated();

        $strandIds = $validated['strand_ids'] ?? [];
        unset($validated['strand_ids']);

        $program = $this->programService->createProgram($validated, $strandIds);

        return response()->json($program, 201);
    }

    /**
     * Update an existing program
     *
     * @param UpdateProgramRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProgramRequest $request, $id)
    {
        $program = $this->programService->findProgramOrFail($id);

        $validatedData = $request->validated();

        $strandIds = $validatedData['strand_ids'] ?? [];
        unset($validatedData['strand_ids']);

        $program = $this->programService->updateProgram($program, $validatedData, $strandIds);

        return response()->json([
            'message' => 'Program updated successfully',
            'program' => $program
        ]);
    }

    /**
     * Delete a program
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $program = $this->programService->findProgramOrFail($id);
            $this->programService->deleteProgram($program);

            return response()->json(['message' => 'Program deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Program not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Program deletion failed', [
                'program_id' => $id,
                'exception_class' => get_class($e),
            ]);
            return response()->json([
                'message' => 'An error occurred while deleting the program. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get all programs for add index
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addindex()
    {
        $programs = $this->programService->getAllPrograms();
        return response()->json($programs);
    }

    /**
     * Get all available strands for dropdown
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStrands()
    {
        return response()->json(Strand::where('is_active', true)->get());
    }
}
