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
use App\Services\DashboardService;
use App\Services\UserService;
use App\Services\ApplicationService;
use Illuminate\Support\Facades\Auth;

class RecordStaffDashboardController extends Controller
{
    use \App\Http\Traits\ManagesApplicationFiles;

    protected DashboardService $dashboardService;
    protected UserService $userService;
    protected ApplicationService $applicationService;

    public function __construct(
        DashboardService $dashboardService,
        UserService $userService,
        ApplicationService $applicationService
    ) {
        $this->dashboardService = $dashboardService;
        $this->userService = $userService;
        $this->applicationService = $applicationService;
    }

    /**
     * Get the current stage name for ManagesApplicationFiles trait
     */
    protected function getCurrentStage(): string
    {
        return 'records';
    }

    /**
     * Get the role ID for ManagesApplicationFiles trait
     */
    protected function getRoleId(): int
    {
        return 6;
    }

    /**
     * Check prerequisite stage - records requires medical to be completed
     */
    protected function checkPrerequisiteStage($application)
    {
        $medicalCompleted = $application->processes()
            ->where('stage', 'medical')
            ->where('status', 'completed')
            ->exists();

        if (!$medicalCompleted) {
            abort(403, 'Cannot proceed - medical stage not completed.');
        }
    }

    /**
     * Display the Record Staff dashboard
     */
    public function index()
    {
        $dashboardData = $this->dashboardService->getRecordsDashboardData();

        return Inertia::render('Dashboard/Records', [
            'user' => Auth::user(),
            'users' => $dashboardData['allUsers']->values()->all(),
            'programs' => $dashboardData['programs']->values()->all(),
            'summary' => $dashboardData['summary'],
        ]);
    }

    /**
     * Get users for the applications page
     * This is the method called by /record-dashboard/applicants
     */
    public function getUsers()
    {
        // Ensure user has records staff role
        $this->ensureRole($this->getRoleId());

        // Return all applicants filtered by records stage (including completed)
        return response()->json(
            $this->userService->getAllApplicantsByStage('records')
        );
    }

    /**
     * Get statistics for the dashboard
     */
    public function getStats()
    {
        $this->ensureRole($this->getRoleId());

        $dashboardData = $this->dashboardService->getRecordsDashboardData();

        return response()->json([
            'summary' => $dashboardData['summary'] ?? [],
        ]);
    }

    /**
     * Get available programs
     */
    public function getPrograms()
    {
        // Use withoutAppends() to prevent automatic loading of strand_names accessor
        $programs = Program::where('slots', '>', 0)
            ->select('id', 'code', 'name', 'slots')
            ->get()
            ->map(function ($program) {
                return [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'slots' => $program->slots,
                ];
            });

        return response()->json([
            'programs' => $programs
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
            'grades',
            'graduateTypes',
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

        $graduateType = $user->graduateTypes->first()?->label ?? null;

        return response()->json([
            'user' => $userData,
            'uploadedFiles' => FileMapper::formatFilesForGraduateType($files, $graduateType, false),
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

    /**
     * Tag an applicant as officially enrolled
     */
    public function tag($id)
    {
        $this->ensureRole($this->getRoleId());

        $application = Application::where('user_id', $id)->firstOrFail();

        // Check if medical is completed
        $medicalCompleted = $application->processes()
            ->where('stage', 'medical')
            ->where('status', 'completed')
            ->exists();

        if (!$medicalCompleted) {
            return response()->json([
                'message' => 'Cannot tag as officially enrolled. Medical process not completed.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $application->update([
                'enrollment_status' => 'officially_enrolled'
            ]);

            // Create or update records process
            ApplicationProcess::updateOrCreate(
                [
                    'application_id' => $application->id,
                    'stage'          => 'records'
                ],
                [
                    'status'         => 'completed',
                    'reviewer_notes' => 'Tagged as officially enrolled',
                    'performed_by'   => auth()->id(),
                    'ip_address'     => request()->ip()
                ]
            );

            DB::commit();
            return response()->json(['message' => 'Tagged as officially enrolled successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to tag: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Untag an applicant from officially enrolled (revert to temporary)
     */
    public function untag($id)
    {
        $this->ensureRole($this->getRoleId());

        $application = Application::where('user_id', $id)->firstOrFail();

        if ($application->enrollment_status !== 'officially_enrolled') {
            return response()->json([
                'message' => 'Application is not officially enrolled.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $application->update([
                'enrollment_status' => 'temporary'
            ]);

            // Update records process
            $recordsProcess = ApplicationProcess::where('application_id', $application->id)
                ->where('stage', 'records')
                ->first();

            if ($recordsProcess) {
                $recordsProcess->update([
                    'status'         => 'in_progress',
                    'reviewer_notes' => 'Reverted to temporary enrolled',
                    'performed_by'   => auth()->id(),
                    'ip_address'     => request()->ip()
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Reverted to temporary enrolled successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to untag: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ensure user has the correct role
     */
    private function ensureRole(int $roleId): void
    {
        if (!Auth::user() || Auth::user()->role_id !== $roleId) {
            abort(403, 'Unauthorized access.');
        }
    }
}
