<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApplicantProfile;
use App\Models\UserFile;
use Inertia\Inertia;
use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;
use App\Helpers\FileMapper;

class RecordStaffDashboardController extends Controller
{
    /**
     * Display the Record Staff dashboard
     */
    public function index()
    {
        $summary = [
            'total' => Application::count(),
            'medical_completed' => Application::whereHas('processes', function ($q) {
                $q->where('stage', 'medical')->where('status', 'completed');
            })->count(),
            'officially_enrolled' => Application::where('enrollment_status', 'officially_enrolled')->count(),
            'pending_records' => Application::whereHas('processes', function ($q) {
                $q->where('stage', 'medical')->where('status', 'completed');
            })->whereDoesntHave('processes', function ($q) {
                $q->where('stage', 'records')->where('status', 'completed');
            })->count(),
        ];

        return Inertia::render('Dashboard/Admin', [
            'user' => auth()->user(),
            'summary' => $summary,
            'isRecordStaff' => true,
        ]);
    }

    /**
     * Get applicants that are eligible for records processing
     * Medical completed OR recently enrolled
     */
    public function getApplicants()
    {
        // For performance, let's select just what we need
        $applicants = ApplicantProfile::with([
            'currentApplication.program',
            'currentApplication.processes' => function ($q) {
                $q->whereIn('stage', ['medical', 'records'])
                    ->where('status', 'completed');
            }
        ])
            ->whereHas('currentApplication', function ($query) {
                $query->where(function ($q) {
                    $q->where('enrollment_status', 'officially_enrolled')
                        ->orWhereHas('processes', function ($pq) {
                            $pq->where('stage', 'medical')->where('status', 'completed');
                        });
                });
            })
            ->get();

        return response()->json(
            $applicants->map(function ($applicant) {
                return [
                    'id' => $applicant->user_id,
                    'firstname' => $applicant->firstname,
                    'lastname' => $applicant->lastname,
                    'email' => $applicant->email,
                    'phone' => $applicant->contactnumber,
                    'application' => $applicant->currentApplication,
                    'program' => $applicant->currentApplication->program ?? null,
                ];
            })
        );
    }

    /**
     * Override getUserFiles to allow record staff to access applications
     * that have completed medical stage or are officially enrolled
     */
    public function getUserFiles($id)
    {
        $user = ApplicantProfile::with([
            'currentApplication.program',
            'currentApplication.processes',
            'grades'
        ])->where('user_id', $id)->firstOrFail();

        $application = $user->currentApplication;

        if (!$application) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        // Check if application has completed medical stage or is officially enrolled
        $hasMedicalCompleted = $application->processes()
            ->where('stage', 'medical')
            ->where('status', 'completed')
            ->exists();

        $isOfficiallyEnrolled = $application->enrollment_status === 'officially_enrolled';

        if (!$hasMedicalCompleted && !$isOfficiallyEnrolled) {
            return response()->json([
                'message' => 'Cannot access files. Medical process not completed.'
            ], 403);
        }

        $files = UserFile::where('user_id', $id)->get()->keyBy('type');

        $userData = [
            'id' => $user->user_id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'files' => $files->values(),
            'grades' => $user->grades,
            'application' => $user->currentApplication,
        ];

        return response()->json([
            'user' => $userData,
            'uploadedFiles' => FileMapper::formatFilesUrls($files),
        ]);
    }

    /**
     * Submit records process for an applicant
     */
    public function submitRecordsProcess(Request $request)
    {
        $request->validate([
            'application_id' => 'required|exists:applications,id',
            'status'         => 'required|in:completed',
            'reviewer_notes' => 'nullable|string'
        ]);

        $application = Application::findOrFail($request->application_id);
        $user = auth()->user();

        // Ensure medical is completed
        $medicalCompleted = $application->processes()
            ->where('stage', 'medical')
            ->where('status', 'completed')
            ->exists();

        if (!$medicalCompleted) {
            return response()->json(['message' => 'Medical assessment must be completed first'], 400);
        }

        DB::beginTransaction();
        try {
            ApplicationProcess::updateOrCreate(
                [
                    'application_id' => $application->id,
                    'stage'          => 'records'
                ],
                [
                    'status'         => $request->status,
                    'reviewer_notes' => $request->reviewer_notes,
                    'performed_by'   => $user ? $user->id : null,
                    'ip_address'     => request()->ip()
                ]
            );

            // Automatically set to officially enrolled if records are completed 
            // and application was accepted
            if ($application->status === 'accepted') {
                $application->update([
                    'enrollment_status' => 'officially_enrolled'
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Records process submitted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to log records process: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Change course/program for an officially enrolled applicant
     */
    public function changeCourse(Request $request, $applicantId)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id'
        ]);

        $newProgramId = $request->program_id;

        // Verify the applicant profile exists
        $applicant = ApplicantProfile::where('user_id', $applicantId)->firstOrFail();

        $application = Application::where('user_id', $applicantId)->firstOrFail();

        if ($application->enrollment_status !== 'officially_enrolled') {
            return response()->json(['message' => 'Course can only be changed for officially enrolled applicants.'], 409);
        }

        if ($application->program_id == $newProgramId) {
            return response()->json(['message' => 'The selected program is the same as the current program.'], 422);
        }

        $oldProgramId = $application->program_id;

        // Perform the update
        DB::beginTransaction();
        try {
            $application->update([
                'program_id' => $newProgramId
            ]);

            // Define user ID safely for mock / idp
            $perfBy = auth()->user() ? auth()->user()->id : null;

            // Log the process
            \App\Models\ApplicationProcess::create([
                'application_id' => $application->id,
                'stage'          => 'course_changed',
                'action'         => 'course_changed',
                'status'         => 'completed',
                'reviewer_notes' => 'Changed from program ID ' . $oldProgramId . ' to ' . $newProgramId,
                'performed_by'   => $perfBy,
                'ip_address'     => request()->ip()
            ]);

            DB::commit();

            $newProgram = Program::find($newProgramId);

            return response()->json([
                'message' => 'Course updated successfully.',
                'program' => $newProgram
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to change course: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export enrolled applicants to CSV
     */
    public function downloadEnrolledCsv()
    {
        $applicants = ApplicantProfile::with(['currentApplication.program'])
            ->whereHas('currentApplication', function ($q) {
                $q->where('enrollment_status', 'officially_enrolled');
            })
            ->get();

        $csv = Writer::createFromString('');
        $csv->insertOne([
            'Applicant ID',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Program Code',
            'Program Name',
            'Enrollment Date'
        ]);

        foreach ($applicants as $applicant) {
            $application = $applicant->currentApplication;
            $program = $application->program;

            $csv->insertOne([
                $applicant->user_id,
                $applicant->firstname,
                $applicant->lastname,
                $applicant->email,
                $applicant->contactnumber,
                $program ? $program->code : 'N/A',
                $program ? $program->name : 'N/A',
                $application->updated_at->format('Y-m-d H:i:s')
            ]);
        }

        return response((string) $csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=\"enrolled_applicants.csv\"',
        ]);
    }
}
