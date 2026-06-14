<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Program;

class StaffProgramController extends Controller
{
    /**
     * Render the Staff Programs page
     */
    public function index()
    {
        $user = Auth::user();
        
        // Ensure user is one of the staff roles
        // 2: Admin, 3: Document Evaluator, 4: Interviewer, 6: Record Staff, 7: Admin2, 8: Grade Evaluator
        if (!in_array($user->role_id, [2, 3, 4, 6, 7, 8])) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        return Inertia::render('Programs/StaffPrograms', [
            'user' => $user,
        ]);
    }

    /**
     * Get programs and slots for the staff dashboard
     */
    public function getPrograms()
    {
        $user = Auth::user();
        
        if (!in_array($user->role_id, [2, 3, 4, 6, 7, 8])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get all programs with their strands
        $programs = Program::with('strands')->orderBy('name')->get();

        $formattedPrograms = $programs->map(function ($program) {
            return [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'slots' => $program->slots,
                'strand_names' => $program->strand_names,
                'requirements' => [
                    'math' => $program->math,
                    'science' => $program->science,
                    'english' => $program->english,
                    'gwa' => $program->gwa,
                ],
            ];
        });

        return response()->json([
            'programs' => $formattedPrograms,
        ]);
    }
}
