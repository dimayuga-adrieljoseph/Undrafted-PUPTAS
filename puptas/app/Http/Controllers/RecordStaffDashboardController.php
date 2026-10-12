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
use App\Helpers\DataMaskingHelper;
use App\Services\DashboardService;
use App\Services\UserService;
use App\Services\ApplicationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\ChangeCourseRequest;

class RecordStaffDashboardController extends Controller
{
    use \App\Http\Traits\ManagesApplicationFiles;

    protected DashboardService $dashboardService;
    protected UserService $userService;
    protected ApplicationService $applicationService;
    protected \App\Services\AuditLogService $auditLogService;
    protected \App\Services\IdpService $idpService;

    public function __construct(
        DashboardService $dashboardService,
        UserService $userService,
        ApplicationService $applicationService,
        \App\Services\AuditLogService $auditLogService,
        \App\Services\IdpService $idpService
    ) {
        $this->dashboardService = $dashboardService;
        $this->userService = $userService;
        $this->applicationService = $applicationService;
        $this->auditLogService = $auditLogService;
        $this->idpService = $idpService;
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
    public function index(Request $request)
    {
        $user = Auth::user();
        $shouldMask = DataMaskingHelper::resolveForRequest($request, $user, 'Records Queue');
        $dashboardData = $this->dashboardService->getRecordsDashboardData($shouldMask);

        $isAdmin = $user && in_array($user->role_id, [2, 7]);

        return Inertia::render('Dashboard/Records', [
            'user'     => $user ? $user->only(['id', 'firstname', 'lastname', 'email', 'role_id']) : null,
            'programs' => $dashboardData['programs']->values()->all(),
            'summary'  => $dashboardData['summary'],
            'baseUrl'  => $isAdmin ? '/admin/records' : '/record-dashboard',
        ]);
    }

    /**
     * Get users for the applications page
     * This is the method called by /record-dashboard/applicants
     */
    public function getUsers(Request $request)
    {
        // Ensure user has records staff role
        $this->ensureRole($this->getRoleId());

        $user = Auth::user();
        $shouldMask = DataMaskingHelper::resolveForRequest($request, $user, 'Records Queue');
        $search = trim((string) ($request->input('search') ?? $request->input('q') ?? ''));

        // Return applicants who have completed medical OR are officially enrolled
        return response()->json(
            $this->userService->getApplicantsForRecordStaff($shouldMask, $search !== '' ? $search : null)
        );
    }

    /**
     * Get statistics for the dashboard
     */
    public function getStats()
    {
        $this->ensureRole($this->getRoleId());

        // Applicants currently cleared for enrollment (in queue for records)
        $inProgress = \App\Models\Application::where('status', 'cleared_for_enrollment')->count();

        // Applicants already officially enrolled (processed by records)
        $processed = \App\Models\Application::where('enrollment_status', 'officially_enrolled')->count();

        $summary = [
            'in_progress' => $inProgress,
            'processed'   => $processed,
        ];

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
    public function getApplicants(Request $request)
    {
        $user = Auth::user();
        $shouldMask = DataMaskingHelper::resolveForRequest($request, $user, 'Records Queue');

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
            $applicants->map(function ($applicant) use ($shouldMask) {
                return [
                    'id' => $applicant->user_id,
                    'firstname' => $shouldMask ? DataMaskingHelper::maskName($applicant->firstname) : $applicant->firstname,
                    'lastname' => $shouldMask ? DataMaskingHelper::maskName($applicant->lastname) : $applicant->lastname,
                    'email' => $shouldMask ? DataMaskingHelper::maskEmail($applicant->email) : $applicant->email,
                    'is_masked' => $shouldMask,
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
                $wasAlreadyEnrolled = $application->enrollment_status === 'officially_enrolled';
                
                $application->update([
                    'status'            => 'accepted',
                    'enrollment_status' => 'officially_enrolled',
                ]);

                // Log enrollment to audit logs (only if newly enrolled)
                if (!$wasAlreadyEnrolled) {
                    try {
                        $user = $application->user;
                        $this->auditLogService->logActivity(
                            'UPDATE',
                            'Enrollment Status',
                            "Automatically tagged student {$user->firstname} {$user->lastname} (User ID: {$user->id}) as officially enrolled after records completion by " . (auth()->user()->username ?? 'system'),
                            $user,
                            \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
                        );
                    } catch (\Exception $auditException) {
                        Log::error('Failed to log automatic enrollment to audit', [
                            'application_id' => $application->id,
                            'error' => $auditException->getMessage(),
                        ]);
                    }
                }

                // Update IDP account type only if this is the first time being enrolled
                if (!$wasAlreadyEnrolled) {
                    $this->dispatchIdpUpdate((string) $application->user_id, $request->input('mfa_code'));
                }
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
     * Also updates the applicant's role in IDP from "applicant" to "student"
     */
    public function tag(Request $request, $id)
    {
        $this->ensureRole($this->getRoleId());

        $application = Application::where('user_id', (string) $id)->firstOrFail();
        $user = $application->user;

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

            // Log enrollment to audit logs
            $this->auditLogService->logActivity(
                'UPDATE',
                'Enrollment Status',
                "Manually tagged student {$user->firstname} {$user->lastname} (User ID: {$user->id}) as officially enrolled by " . auth()->user()->username,
                $user,
                \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
            );

            DB::commit();

            // Update the applicant's role in IDP from "applicant" to "student" via queue
            $this->dispatchIdpUpdate($id, $request->input('mfa_code'));

            // Return updated application data so frontend can update without full reload
            $application->refresh();

            return response()->json([
                'message'            => 'Tagged as officially enrolled successfully',
                'enrollment_status'  => $application->enrollment_status,
                'application_status' => $application->status,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log failed enrollment attempt
            try {
                $this->auditLogService->logActivity(
                    'FAILED',
                    'Enrollment Status',
                    "Failed to tag student (User ID: {$id}) as officially enrolled: " . $e->getMessage(),
                    $user ?? null,
                    \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
                );
            } catch (\Exception $auditException) {
                Log::error('Failed to log enrollment failure to audit', [
                    'error' => $auditException->getMessage(),
                ]);
            }
            
            return response()->json(['message' => 'Failed to tag: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Dispatch IDP account type update to queue
     * This queues a job to update the applicant's account type in IDP from "applicant" to "student"
     * 
     * SECURITY: This method should only be called after proper authorization checks
     * 
     * @param string $userId The local user ID
     * @param string|null $mfaCode Optional MFA code if required by IDP
     * @return void
     */
    private function dispatchIdpUpdate(string $userId, ?string $mfaCode = null): void
    {
        try {
            // SECURITY: Verify the current user is authorized to perform this action
            $currentUser = auth()->user();
            if (!$currentUser || !in_array($currentUser->role_id, [2, 6, 7])) {
                Log::warning('Unauthorized attempt to update IDP account type', [
                    'user_id' => $userId,
                    'attempted_by' => $currentUser?->id ?? 'unauthenticated',
                ]);
                return;
            }

            // Get the applicant's IDP user ID from the User model
            $user = \App\Models\User::where('id', $userId)->first();

            if (!$user) {
                Log::warning('User not found when attempting IDP account type update', [
                    'user_id' => $userId,
                ]);
                return;
            }

            // SECURITY: Only update IDP for users who have IDP accounts
            // This prevents attempting to modify non-IDP users
            $idpUserId = $user->idp_user_id;

            if (empty($idpUserId)) {
                Log::warning('User does not have an IDP user ID, skipping IDP account type update', [
                    'user_id' => $userId,
                ]);
                return;
            }

            // SECURITY: Get the admin's access token from session (not exposed to client)
            $accessToken = $this->idpService->getAdminAccessToken();

            if (empty($accessToken)) {
                Log::error('Admin access token not available for IDP account type update', [
                    'user_id' => $userId,
                    'idp_user_id' => $idpUserId,
                    'attempted_by' => $currentUser->id ?? 'unknown',
                ]);
                return;
            }

            // SECURITY: Validate MFA code format if provided
            if ($mfaCode !== null && !preg_match('/^\d{6}$/', $mfaCode)) {
                Log::warning('Invalid MFA code format provided', [
                    'user_id' => $userId,
                    'attempted_by' => $currentUser->id ?? 'unknown',
                ]);
                // Continue without MFA code rather than failing
                $mfaCode = null;
            }

            // Get user email for job logging
            $userEmail = $user->email ?? 'N/A';

            // Dispatch the UpdateIdpAccountType job to the queue
            \App\Jobs\UpdateIdpAccountType::dispatch(
                (int) $userId,  // Cast to int to match job constructor
                $idpUserId,
                $userEmail,
                $accessToken,
                $mfaCode,
                $currentUser->id ?? null,
                false // not from sync command
            );

            Log::info('Dispatched IDP account type update job', [
                'user_id' => $userId,
                'idp_user_id' => $idpUserId,
                'performed_by' => $currentUser->id ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            // SECURITY: Catch any exceptions to prevent blocking the enrollment process
            // and prevent information leakage about internal systems
            Log::error('Exception during IDP account type update', [
                'user_id' => $userId,
                'exception' => $e->getMessage(),
                'attempted_by' => auth()->user()?->id ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Untag an applicant from officially enrolled (revert to temporary)
     */
    public function untag($id)
    {
        $this->ensureRole($this->getRoleId());

        $application = Application::where('user_id', (string) $id)->firstOrFail();
        $user = $application->user;

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

            // Log untag to audit logs
            $this->auditLogService->logActivity(
                'UPDATE',
                'Enrollment Status',
                "Reverted student {$user->firstname} {$user->lastname} (User ID: {$user->id}) from officially enrolled to temporary by " . auth()->user()->username,
                $user,
                \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
            );

            DB::commit();
            return response()->json(['message' => 'Reverted to temporary enrolled successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log failed untag attempt
            try {
                $this->auditLogService->logActivity(
                    'FAILED',
                    'Enrollment Status',
                    "Failed to untag student (User ID: {$id}) from officially enrolled: " . $e->getMessage(),
                    $user ?? null,
                    \App\Models\AuditLog::CATEGORY_SYSTEM_CHANGE
                );
            } catch (\Exception $auditException) {
                Log::error('Failed to log untag failure to audit', [
                    'error' => $auditException->getMessage(),
                ]);
            }
            
            return response()->json(['message' => 'Failed to untag: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ensure user has the correct role.
     * Admin (2) and Superadmin (7) are always permitted alongside the given role.
     */
    private function ensureRole(int $roleId): void
    {
        $user = Auth::user();
        $allowedRoles = [$roleId, 2, 7];
        if (!$user || !in_array($user->role_id, $allowedRoles)) {
            abort(403, 'Unauthorized access.');
        }
    }
}
