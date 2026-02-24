<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Program;
use App\Models\Strand;
use App\Models\AuditLog;
use App\Rules\ValidationRules;
use Illuminate\Support\Facades\Auth;

class ProgramController extends Controller
{
    // ✅ Fetch all programs with strands
    public function index()
    {
        $programs = Program::with('strands')->get();
        return response()->json($programs);
    }


    // ✅ Create a new program
    public function store(Request $request)
    {
        $validated = $request->validate(ValidationRules::programStore());

        $strandIds = $validated['strand_ids'] ?? [];
        unset($validated['strand_ids']);

        $program = Program::create($validated);
        
        // Attach strands to program
        if (!empty($strandIds)) {
            $program->strands()->attach($strandIds);
        }

        // Load strands for response
        $program->load('strands');

        // Audit log for program creation
        AuditLog::create([
            'user_id' => Auth::id(),
            'model_type' => Program::class,
            'model_id' => $program->id,
            'action' => 'created',
            'old_values' => null,
            'new_values' => $program->toArray(),
            'ip_address' => $request->ip(),
        ]);
        
        return response()->json($program, 201);
    }

    public function update(Request $request, $id)
    {
        // ✅ Retrieve the program before updating
        $program = Program::find($id);

        // ✅ Check if the program exists
        if (!$program) {
            return response()->json(['message' => 'Program not found'], 404);
        }

        // Store old values for audit log
        $oldValues = $program->toArray();

        // ✅ Validate the request data
        $validatedData = $request->validate(ValidationRules::programUpdate($id));

        $strandIds = $validatedData['strand_ids'] ?? [];
        unset($validatedData['strand_ids']);

        // ✅ Update the program
        $program->update($validatedData);

        // Sync strands (this will add new ones and remove ones not in the array)
        $program->strands()->sync($strandIds);

        // Load strands for response
        $program->load('strands');

        // Audit log for program update
        AuditLog::create([
            'user_id' => Auth::id(),
            'model_type' => Program::class,
            'model_id' => $program->id,
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $program->fresh()->toArray(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Program updated successfully', 'program' => $program]);
    }

    // ✅ Delete a program
    public function destroy(Request $request, $id)
    {
        try {
            $program = Program::findOrFail($id);
            
            // Store old values for audit log before deletion
            $oldValues = $program->toArray();
            
            // Detach all strands before deleting (optional, cascade should handle this)
            $program->strands()->detach();
            $program->delete();

            // Audit log for program deletion
            AuditLog::create([
                'user_id' => Auth::id(),
                'model_type' => Program::class,
                'model_id' => $id,
                'action' => 'deleted',
                'old_values' => $oldValues,
                'new_values' => null,
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Program deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Program not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Program deletion failed', [
                'program_id' => $id,
                'user_id' => Auth::id(),
                'exception_class' => get_class($e),
            ]);
            return response()->json(['message' => 'An error occurred while deleting the program. Please try again later.'], 500);
        }
    }
    
    public function addindex()
    {
        return response()->json(Program::all()); // Or return data for Vue.js
    }

    /**
     * Get all available strands for dropdown
     */
    public function getStrands()
    {
        return response()->json(Strand::where('is_active', true)->get());
    }
}
