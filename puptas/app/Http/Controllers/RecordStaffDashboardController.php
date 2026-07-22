<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
use App\Http\Requests\ChangeCourseRequest;

class RecordStaffDashboardController extends Controller
{
    use \App\Http\Traits\ManagesApplicationFiles;

    protected DashboardService $dashboardService;
    protected UserService $userService;
    protected ApplicationService $applicationService;
    protected \App\Services\AuditLogService $auditLogService;

    public function __construct(
        DashboardService $dashboardService,
        UserService $userService,
        ApplicationService $applicationService,
        \App\Services\AuditLogService $auditLogService
    ) {
        $this->dashboardService = $dashboardService;
        $this->userService = $userService;
        $this->applicationService = $applicationService;
        $this->auditLogService = $auditLogService;
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
            'user' => Auth::user() ? Auth::user()->only(['id', 'firstname', 'lastname', 'email', 'role_id']) : null,
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

        // Return applicants who have completed medical OR are officially enrolled
        return response()->json(
            $this->userService->getApplicantsForRecordStaff()
        );
    }

    /**
     * Get statistics for the dashboard
     */
    public function getStats()
    {
        $this->ensureRole($this->getRoleId());

        $summary = $this->applicationService->getApplicationSummary();

        // Count officially enrolled and accepted per program
        $programs = Program::select('id', 'code', 'name', 'slots')
            ->withCount([
                'applications as enrolled_count' => function ($q) {
                    $q->where('enrollment_status', 'officially_enrolled')
                      ->whereNull('deleted_at');
                },
                'applications as medical_cleared_count' => function ($q) {
                    $q->whereHas('processes', function ($p) {
                        $p->where('stage', 'medical')->where('status', 'completed');
                    })->whereNull('deleted_at');
                },
                'applications as accepted_count' => function ($q) {
                    $q->whereHas('processes', function ($p) {
                        $p->where('stage', 'interviewer')
                          ->where('status', 'completed')
                          ->where('action', 'passed');
                    })->whereNull('deleted_at');
                },
            ])
            ->get()
            ->map(function ($program) {
                return [
                    'id'                    => $program->id,
                    'code'                  => $program->code,
                    'name'                  => $program->name,
                    'slots'                 => $program->slots,
                    'applications_count'    => $program->accepted_count,
                    'enrolled_count'        => $program->enrolled_count,
                    'medical_cleared_count' => $program->medical_cleared_count,
                ];
            });

        return response()->json([
            'summary'  => $summary,
            'programs' => $programs,
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
                $q->select('id', 'application_id', 'stage', 'status', 'action', 'created_at')
                    ->whereIn('stage', ['medical', 'records'])
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
                    'application' => $applicant->currentApplication,
                    'program' => $applicant->currentApplication->program ?? null,
                ];
            })
        );
    }

    /**
     * Override getUserFiles to allow record staff to access applications
     * that have completed medical stage or are officially enrolled
     * OPTIMIZED: Loads only essential data, files loaded separately
     */
    public function getUserFiles($id)
    {
        try {
            // Load applicant with essential data only (no files relationship)
            $user = ApplicantProfile::with([
                'currentApplication' => function ($query) {
                    $query->select('applications.id', 'applications.user_id', 'applications.status', 'applications.created_at', 'applications.program_id', 'applications.enrollment_status');
                },
                'currentApplication.program:id,code,name,slots',
                'currentApplication.processes' => function ($query) {
                    $query->select('id', 'application_id', 'stage', 'status', 'action', 'reviewer_notes', 'created_at')
                        ->orderBy('created_at', 'desc')
                        ->limit(10);
                },
                'grades',
                'graduateTypes:id,label',
                'testPasser',
            ])
            ->where('user_id', (string) $id)
            ->firstOrFail();

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

            // Load files separately for better performance
            $files = UserFile::where('user_id', (string) $id)->get()->keyBy('type');

            $userData = [
                'id' => $user->user_id,
                'reference_number' => $user->testPasser->reference_number ?? 'N/A',
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'sex' => $user->sex,
                'created_at' => $user->created_at,
                'grades' => $user->grades ? collect($user->grades->toArray())->except(['id', 'user_id', 'created_at', 'updated_at'])->toArray() : null,
                'application' => [
                    'id' => $application->id,
                    'status' => $application->status,
                    'enrollment_status' => $application->enrollment_status,
                    'created_at' => $application->created_at,
                    'program' => $application->program,
                    'processes' => $application->processes,
                ],
            ];

            $graduateType = $user->graduateTypes->first()?->label ?? null;



            return response()->json([
                'user' => $userData,
                'uploadedFiles' => FileMapper::formatFilesForGraduateType($files, $graduateType, false, true),
                'lazyLoad' => false,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Applicant not found in registrar getUserFiles', [
                'userId' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Applicant not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to load applicant data in registrar', [
                'userId' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to load applicant data. Please try again.',
            ], 500);
        }
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

        // Check if COR is uploaded
        $corFront = \App\Models\UserFile::where('user_id', (string) $application->user_id)->where('type', 'cor_front')->first();
        $corBack = \App\Models\UserFile::where('user_id', (string) $application->user_id)->where('type', 'cor_back')->first();

        if (!$corFront || !$corBack || !$corFront->isUploaded() || !$corBack->isUploaded()) {
            return response()->json(['message' => 'Applicant must upload COR Front and Back before records processing.'], 400);
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
            // and application was cleared for enrollment or accepted
            if (in_array($application->status, ['cleared_for_enrollment', 'accepted'])) {
                $application->update([
                    'status'            => 'accepted',
                    'enrollment_status' => 'officially_enrolled',
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
     * Change course/program for an applicant based on role and enrollment status
     * 
     * Interviewers can change courses for non-officially-enrolled applicants (pending, temporary)
     * Admins and Superadmins can change courses regardless of enrollment status
     */
    public function changeCourse(ChangeCourseRequest $request, string $applicantId): JsonResponse
    {
        // Retrieve Application by user_id matching $applicantId
        $application = Application::where('user_id', (string) $applicantId)->first();
        
        // Return 404 JSON response if application not found
        if (!$application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }
        
        // Call authorization check - Laravel will automatically return 403 if authorization fails
        $this->authorize('changeCourse', $application);

        $request->validate([
            'program_id' => 'required|exists:programs,id'
        ]);

        $newProgramId = $request->program_id;

        if ($application->program_id == $newProgramId) {
            return response()->json(['message' => 'The selected program is the same as the current program.'], 422);
        }

        // Perform the update within a database transaction
        DB::beginTransaction();
        try {
            // Store old program_id and enrollment_status before update
            $oldProgramId = $application->program_id;
            $isOfficiallyEnrolled = $application->enrollment_status === 'officially_enrolled';
            
            // Update application program_id
            // If officially enrolled, keep status as 'accepted' and enrollment_status as 'officially_enrolled'
            // If not officially enrolled, set status to 'transferred' (for interviewers during interview stage)
            $updateData = ['program_id' => $newProgramId];
            
            // Only change status to 'transferred' if NOT officially enrolled
            if (!$isOfficiallyEnrolled && $application->status !== 'transferred') {
                $updateData['status'] = 'transferred';
            }
            
            $application->update($updateData);

            // Safely compute performed_by: only use numeric IDs for FK constraint
            $authUser = auth()->user();
            $performedBy = null;
            if ($authUser) {
                $userId = $authUser->id ?? $authUser->idp_user_id ?? null;
                $performedBy = ($userId !== null && is_numeric($userId)) ? (int)$userId : null;
            }

            // Create ApplicationProcess record with action='course_changed', stage='records', status='completed'
            ApplicationProcess::create([
                'application_id' => $application->id,
                'stage'          => 'records',
                'action'         => 'course_changed',
                'status'         => 'completed',
                'reviewer_notes' => 'Changed from program ID ' . $oldProgramId . ' to ' . $newProgramId . 
                                   ($isOfficiallyEnrolled ? ' (officially enrolled - status preserved)' : ''),
                'performed_by'   => $performedBy,
                'ip_address'     => request()->ip()
            ]);

            DB::commit();

            // Log successful course change - handle failures gracefully
            try {
                $user = auth()->user();
                $this->auditLogService->logActivity(
                    'UPDATE',
                    'Applications',
                    "Changed course for applicant {$applicantId} from program {$oldProgramId} to {$newProgramId}",
                    $user,
                    \App\Models\AuditLog::CATEGORY_ADMISSION_DATA
                );
            } catch (\Throwable $e) {
                // Log the error but don't fail the operation
                logger()->error('[RecordStaffDashboard] Failed to log course change audit', [
                    'applicant_id' => $applicantId,
                    'old_program_id' => $oldProgramId,
                    'new_program_id' => $newProgramId,
                    'error' => $e->getMessage(),
                ]);
            }

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

        $application = Application::where('user_id', (string) $id)->firstOrFail();

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

        // Check if COR is uploaded
        $corFront = \App\Models\UserFile::where('user_id', (string) $id)->where('type', 'cor_front')->first();
        $corBack = \App\Models\UserFile::where('user_id', (string) $id)->where('type', 'cor_back')->first();

        if (!$corFront || !$corBack || !$corFront->isUploaded() || !$corBack->isUploaded()) {
            return response()->json([
                'message' => 'Cannot tag as officially enrolled. Applicant must upload COR Front and Back.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $application->update([
                'status'            => 'accepted',
                'enrollment_status' => 'officially_enrolled',
            ]);

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

            // Return updated application data so frontend can update without full reload
            $application->refresh();

            return response()->json([
                'message'            => 'Tagged as officially enrolled successfully',
                'enrollment_status'  => $application->enrollment_status,
                'application_status' => $application->status,
            ]);
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

        $application = Application::where('user_id', (string) $id)->firstOrFail();

        if ($application->enrollment_status !== 'officially_enrolled') {
            return response()->json([
                'message' => 'Application is not officially enrolled.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $application->update([
                'status'            => 'cleared_for_enrollment',
                'enrollment_status' => 'temporary',
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
