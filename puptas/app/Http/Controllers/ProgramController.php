<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Program;
use App\Models\Strand;
use App\Rules\ValidationRules;

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

        return response()->json(['message' => 'Program updated successfully', 'program' => $program]);
    }

    // ✅ Delete a program
    public function destroy($id)
    {
        try {
            $program = Program::findOrFail($id);
            // Detach all strands before deleting (optional, cascade should handle this)
            $program->strands()->detach();
            $program->delete();
            return response()->json(['message' => 'Program deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Program not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Error deleting program: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete program: ' . $e->getMessage()], 500);
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
