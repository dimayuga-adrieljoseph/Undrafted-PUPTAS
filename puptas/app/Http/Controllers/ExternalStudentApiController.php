<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalStudentApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1',
            'updated_since' => 'nullable|date',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 100);

        $query = Application::query()
            ->with(['user', 'program'])
            ->where('enrollment_status', 'officially_enrolled')
            ->orderBy('id');

        if (! empty($validated['updated_since'])) {
            $query->where('updated_at', '>', $validated['updated_since']);
        }

        $paginated = $query->paginate($perPage)->appends($request->query());

        $data = $paginated->getCollection()->map(function (Application $application) {
            $user = $application->user;
            $program = $application->program;

            return [
                'id' => $user?->id,
                'student_number' => $user?->student_number,
                'firstname' => $user?->firstname,
                'middlename' => $user?->middlename,
                'extension_name' => $user?->extension_name,
                'lastname' => $user?->lastname,
                'email' => $user?->email,
                'contactnumber' => $user?->contactnumber,
                'birthday' => $user?->birthday,
                'sex' => $user?->sex,
                'street_address' => $user?->street_address,
                'barangay' => $user?->barangay,
                'city' => $user?->city,
                'province' => $user?->province,
                'postal_code' => $user?->postal_code,
                'application' => [
                    'application_id' => $application->id,
                    'status' => $application->status,
                    'enrollment_status' => $application->enrollment_status,
                    'enrollment_position' => $application->enrollment_position,
                    'submitted_at' => $application->submitted_at,
                ],
                'program' => [
                    'program_id' => $program?->id,
                    'program_code' => $program?->code,
                    'program_name' => $program?->name,
                ],
                'created_at' => $user?->created_at,
                'updated_at' => $user?->updated_at,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }
}
