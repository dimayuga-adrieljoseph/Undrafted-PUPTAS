<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Program;

class ProgramController extends Controller
{
    // ✅ Fetch all programs
    public function index()
    {
        return response()->json(Program::all()); // Or return data for Vue.js
    }
    

    // ✅ Create a new program
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|unique:programs',
            'name' => 'required',
            'strand' => 'nullable|string',
            'math' => 'nullable|integer',
            'science' => 'nullable|integer',
            'english' => 'nullable|integer',
            'gwa' => 'nullable|integer',
            'pupcet' => 'nullable|integer',
            'slots' => 'required|integer'
        ]);

        $program = Program::create($validated);
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
        $validatedData = $request->validate([
            'code' => 'required|string|unique:programs,code,' . $id,
            'name' => 'required|string',
            'strand' => 'nullable|string',
            'math' => 'nullable|integer|min:0',
            'science' => 'nullable|integer|min:0',
            'english' => 'nullable|integer|min:0',
            'gwa' => 'nullable|integer|min:0',
            'pupcet' => 'nullable|integer|min:0',
            'slots' => 'required|integer|min:0',
        ]);

        // ✅ Update the program
        $program->update($validatedData);

        return response()->json(['message' => 'Program updated successfully', 'program' => $program]);
    }

    // ✅ Delete a program
    public function destroy($id)
    {
        try {
            $program = Program::findOrFail($id);
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

}
