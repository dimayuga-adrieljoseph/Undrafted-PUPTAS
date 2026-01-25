<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Grade;
use App\Models\User;
use App\Rules\ValidationRules;

class GradesController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(ValidationRules::gradeImport());
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            sleep(2); // Wait 1 second and try again
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['message' => 'User not found. Please try again.'], 404);
            }
        }

        Grade::updateOrCreate(
            ['user_id' => $user->id],
            [
                'english' => $request->english,
                'mathematics' => $request->mathematics,
                'science' => $request->science,
                'g11_first_sem' => $request->g11_first_sem,
                'g11_second_sem' => $request->g11_second_sem,
                'g12_first_sem' => $request->g12_first_sem,
                'g12_second_sem' => $request->g12_second_sem,
            ]
        );


        return response()->json(['message' => 'Grades saved successfully']);
    }
}
