<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\GradesController;
use App\Http\Controllers\ApplicantDashboardController;
use App\Http\Controllers\TestPasserController;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ConfirmationController;
use App\Http\Controllers\UserFileController;
use App\Http\Controllers\EvaluatorDashboardController;
use App\Http\Controllers\InterviewerDashboardController;
use App\Http\Controllers\RecordStaffDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Admin\Notify\Notify;
use App\Http\Controllers\PrivacyConsentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CallbackController;
use App\Http\Controllers\IdpAuthController;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminOrRegistrar;
use App\Http\Controllers\GradeExtractionController;

// TEMPORARY: Assign student number
Route::post('/debug-medical/assign-student-number/{idpUserId}/{secret}', function ($idpUserId, $secret) {
    if ($secret !== 'debug2026') {
        return response()->json(['error' => 'Invalid secret'], 403);
    }
    
    try {
        $user = \App\Models\User::where('idp_user_id', $idpUserId)->first();
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        if ($user->applicantProfile?->student_number) {
            return response()->json([
                'status' => 'already_has_number',
                'student_number' => $user->applicantProfile->student_number
            ]);
        }
        
        // Generate student number (format: YYYY-MED-XXXX)
        $year = date('Y');
        $lastNumber = \App\Models\ApplicantProfile::where('student_number', 'LIKE', "$year-MED-%")
            ->orderBy('student_number', 'desc')
            ->value('student_number');
        
        if ($lastNumber) {
            $lastNum = (int) substr($lastNumber, -4);
            $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNum = '0001';
        }
        
        $studentNumber = "$year-MED-$newNum";
        $user->applicantProfile->update(['student_number' => $studentNumber]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Student number assigned',
            'student_number' => $studentNumber,
            'user_id' => $user->id
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

// TEMPORARY: Manually complete medical
Route::post('/debug-medical/complete-medical/{idpUserId}/{secret}', function ($idpUserId, $secret) {
    if ($secret !== 'debug2026') {
        return response()->json(['error' => 'Invalid secret'], 403);
    }
    
    try {
        $user = \App\Models\User::where('idp_user_id', $idpUserId)->first();
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        $application = $user->applications()->latest()->first();
        
        if (!$application) {
            return response()->json(['error' => 'No application found'], 404);
        }
        
        // Check if medical already completed
        $medicalProcess = $application->processes()
            ->where('stage', 'medical')
            ->where('status', 'completed')
            ->first();
        
        if ($medicalProcess) {
            return response()->json([
                'status' => 'already_completed',
                'message' => 'Medical already completed',
                'completed_at' => $medicalProcess->created_at
            ]);
        }
        
        // Update medical process
        \App\Models\ApplicationProcess::updateOrCreate(
            [
                'application_id' => $application->id,
                'stage' => 'medical'
            ],
            [
                'status' => 'completed',
                'action' => 'passed',
                'performed_by' => null,
                'reviewer_notes' => 'Manually approved - webhook issue resolved'
            ]
        );
        
        // Update application status
        $application->update(['status' => 'cleared_for_enrollment']);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Medical completed successfully',
            'application_status' => 'cleared_for_enrollment',
            'visible_to_registrar' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/debug-medical/{idpUserId}/{secret}', function ($idpUserId, $secret) {
    // Simple secret check instead of auth
    if ($secret !== 'debug2026') {
        return response()->json(['error' => 'Invalid secret'], 403);
    }
    
    try {
        $user = \App\Models\User::where('idp_user_id', $idpUserId)->first();
        
        if (!$user) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'User does not exist in production database',
                'idp_user_id' => $idpUserId
            ]);
        }
        
        $application = $user->applications()->latest()->first();
        $processes = $application ? $application->processes()->orderBy('created_at')->get() : collect();
        
        $evaluatorCompleted = $processes->where('stage', 'evaluator')
            ->where('status', 'completed')
            ->whereIn('action', ['passed', 'transferred'])
            ->isNotEmpty();
        
        $interviewerCompleted = $processes->where('stage', 'interviewer')
            ->where('status', 'completed')
            ->whereIn('action', ['passed', 'transferred'])
            ->isNotEmpty();
        
        $medicalInProgress = $processes->where('stage', 'medical')
            ->whereIn('status', ['in_progress', 'returned'])
            ->isNotEmpty();
        
        $medicalCompleted = $processes->where('stage', 'medical')
            ->where('status', 'completed')
            ->isNotEmpty();
        
        $auditLogs = \App\Models\AuditLog::where('module_name', 'LIKE', '%Medical%')
            ->where(function($q) use ($user, $idpUserId) {
                $q->where('description', 'LIKE', '%' . $user->student_number . '%')
                  ->orWhere('description', 'LIKE', '%' . $idpUserId . '%');
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        return response()->json([
            'status' => 'found',
            'user' => [
                'id' => $user->id,
                'student_number' => $user->student_number,
                'email' => $user->email,
                'role_id' => $user->role_id,
            ],
            'application' => $application ? [
                'id' => $application->id,
                'status' => $application->status,
                'enrollment_status' => $application->enrollment_status,
                'program_id' => $application->program_id,
                'created_at' => $application->created_at,
            ] : null,
            'processes' => $processes->map(fn($p) => [
                'stage' => $p->stage,
                'status' => $p->status,
                'action' => $p->action,
                'created_at' => $p->created_at,
            ])->values(),
            'eligibility' => [
                'evaluator_completed' => $evaluatorCompleted,
                'interviewer_completed' => $interviewerCompleted,
                'medical_in_progress' => $medicalInProgress,
                'medical_completed' => $medicalCompleted,
                'can_receive_webhook' => $evaluatorCompleted && $interviewerCompleted && $medicalInProgress && !$medicalCompleted,
                'visible_to_registrar' => $medicalCompleted || ($application && $application->enrollment_status === 'officially_enrolled'),
            ],
            'audit_logs' => $auditLogs->map(fn($log) => [
                'created_at' => $log->created_at,
                'action_type' => $log->action_type,
                'description' => $log->description,
            ])->values(),
            'diagnosis' => $medicalCompleted 
                ? '✅ Medical already completed - should be visible to registrar'
                : ($evaluatorCompleted && $interviewerCompleted && $medicalInProgress
                    ? '⚠️ Eligible for webhook but not completed - webhook may not have been received'
                    : '❌ Not eligible for medical webhook - prerequisite stages not completed'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : 'Enable debug mode for trace'
        ], 500);
    }
});

Route::get('/debug-records/{secret}', function ($secret) {
    if ($secret !== 'debug2026') {
        return response()->json(['error' => 'Invalid secret'], 403);
    }

    // Raw query - bypass all relationships
    $results = \Illuminate\Support\Facades\DB::select("
        SELECT 
            ap.user_id,
            ap.firstname,
            ap.lastname,
            ap.student_number,
            a.id as app_id,
            a.status as app_status,
            a.enrollment_status,
            p.stage,
            p.status as process_status,
            p.action
        FROM applicant_profiles ap
        JOIN applications a ON a.user_id = ap.user_id
        JOIN application_processes p ON p.application_id = a.id
        WHERE p.stage = 'medical' AND p.status = 'completed'
        AND a.deleted_at IS NULL
        ORDER BY p.created_at DESC
        LIMIT 20
    ");

    return response()->json([
        'count' => count($results),
        'students' => $results,
    ]);
});

// IDP Authentication Routes - No middleware restrictions so stale sessions don't block the OAuth flow
Route::get('/auth/idp/redirect', [IdpAuthController::class, 'login'])
    ->name('idp.redirect');

Route::get('/auth/idp/callback', [IdpAuthController::class, 'callback'])
    ->name('idp.callback');

Route::get('/auth/callback', [IdpAuthController::class, 'callback'])
    ->name('idp.callback.alias');

Route::post('/api/v1/auth/logout', [IdpAuthController::class, 'logout'])
    ->middleware('auth')
    ->name('idp.logout');


// Backward-compatible callback aliases in case IDP client is configured with older paths.
Route::get('/callback', [IdpAuthController::class, 'callback'])
    ->middleware('guest')
    ->name('idp.callback.legacy');

Route::get('/api/callback', [IdpAuthController::class, 'callback'])
    ->middleware('guest')
    ->name('idp.callback.api-legacy');

// View applicant details route - expects user ID, restricted to admin, evaluator, and interviewer
Route::get('/applications/user/{user}', function ($user) {
    // Validate ID is numeric
    if (!is_numeric($user)) {
        abort(404);
    }

    // Verify user exists and is an applicant
    $applicant = \App\Models\User::where('id', $user)
        ->where('role_id', 1)
        ->whereHas('currentApplication')
        ->first();

    if (!$applicant) {
        abort(404);
    }

    return Inertia::render('Applications/Index', [
        'selectedUserId' => (int) $user
    ]);
})->middleware(['auth', 'role:2,3,4,7'])->whereNumber('user')->name('applications.show');

Route::post('/check-email', function (\Illuminate\Http\Request $request) {
    $request->validate(['email' => 'required|email']);
    $exists = \App\Models\User::where('email', $request->email)->exists();
    return response()->json(['taken' => $exists]);
})->middleware('auth');

Route::middleware(['auth'])->group(function () {
    // Privacy Consent Routes - available to all authenticated users
    Route::post('/privacy-consent/accept', [PrivacyConsentController::class, 'accept'])->name('privacy.consent.accept');
    Route::get('/privacy-consent/check', [PrivacyConsentController::class, 'check'])->name('privacy.consent.check');

    Route::get('/programs', function () {
        return Inertia::render('Programs/Index');
    })->name('programs.index');

    Route::get('/addindex', function () {
        return Inertia::render('Programs/Create');
    })->name('programs.addindex');

    Route::get('/programs/list', [ProgramController::class, 'index'])->name('programs.list');
    Route::get('/programs/strands', [ProgramController::class, 'getStrands'])->name('programs.strands');
    Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
    Route::put('/programs/{program}', [ProgramController::class, 'update'])->name('programs.web-update');
    Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->name('programs.web-delete');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/applicant-dashboard', [ApplicantDashboardController::class, 'index'])
        ->name('applicant.dashboard');

    Route::middleware(['throttle:grade-extraction'])
        ->post('/api/grades/extract', [GradeExtractionController::class, 'extract']);

    Route::get('/grades/abm', [GradesController::class, 'showAbmGradeForm'])->name('grades.abm.form');
    Route::post('/grades/abm', [GradesController::class, 'storeAbmGrades'])->name('grades.abm.store');
    Route::get('/grades/ict', [GradesController::class, 'showIctGradeForm'])->name('grades.ict.form');
    Route::post('/grades/ict', [GradesController::class, 'storeAbmGrades'])->name('grades.ict.store');
    Route::get('/grades/humss', [GradesController::class, 'showHumssGradeForm'])->name('grades.humss.form');
    Route::post('/grades/humss', [GradesController::class, 'storeHumssGrades'])->name('grades.humss.store');
    Route::get('/grades/gas', [GradesController::class, 'showGasGradeForm'])->name('grades.gas.form');
    Route::post('/grades/gas', [GradesController::class, 'storeGasGrades'])->name('grades.gas.store');
    Route::get('/grades/stem', [GradesController::class, 'showStemGradeForm'])->name('grades.stem.form');
    Route::post('/grades/stem', [GradesController::class, 'storeStemGrades'])->name('grades.stem.store');
    Route::get('/grades/tvl', [GradesController::class, 'showTvlGradeForm'])->name('grades.tvl.form');
    Route::post('/grades/tvl', [GradesController::class, 'storeTvlGrades'])->name('grades.tvl.store');
});

Route::get('/home', function () {
    $roleId = Auth::user()->role_id;

    if ($roleId == 1) {
        $hasGrades = \App\Models\Grade::where('user_id', Auth::id())->exists();

        if ($hasGrades) {
            return redirect('/applicant-dashboard');
        } else {
            $profile = \App\Models\ApplicantProfile::where('user_id', Auth::id())->first();
            $strand = $profile?->strand;

            if (!$strand) {
                return redirect('/applicant-dashboard');
            }

            $strandUpper = strtoupper(trim($strand));
            switch ($strandUpper) {
                case 'ABM':
                    return redirect('/grades/abm');
                case 'ICT':
                    return redirect('/grades/ict');
                case 'HUMSS':
                    return redirect('/grades/humss');
                case 'GAS':
                    return redirect('/grades/gas');
                case 'STEM':
                    return redirect('/grades/stem');
                case 'TVL':
                case 'SPORTS':
                case 'ARTS':
                    return redirect('/applicant-dashboard');
                default:
                    return redirect('/applicant-dashboard');
            }
        }
    }

    if ($roleId == 2) return redirect('/dashboard');
    if ($roleId == 3) return redirect('/evaluator-dashboard');
    if ($roleId == 4) return redirect('/interviewer-dashboard');
    if ($roleId == 6) return redirect('/record-dashboard');
    if ($roleId == 7) return redirect('/dashboard');

    return redirect('/');
})->middleware(['auth'])->name('home');

Route::middleware(['auth'])->post('/test-passers/upload', [Notify::class, 'handleUpload']);
Route::middleware(['auth'])->get('/test-passers/form', [Notify::class, 'showUploadForm'])->name('upload.form');

Route::get('/sar/download/{filename}/{reference}', [TestPasserController::class, 'downloadSar'])
    ->name('sar.passer-download');

Route::get('/applications', function () {
    return Inertia::render('Applications/Index');
})->middleware(['auth', EnsureAdmin::class])->name('applications');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/test-passers', [TestPasserController::class, 'index'])->name('lists');
    Route::post('/test-passers/send-emails', [TestPasserController::class, 'sendEmails']);

    Route::middleware(['auth', EnsureAdminOrRegistrar::class])->group(function () {
        Route::get('/admin/sar-generations', [TestPasserController::class, 'getSarGenerations'])->name('admin.sar-generations');
        Route::get('/admin/sar/{id}/download', [TestPasserController::class, 'adminDownloadSar'])->name('admin.sar-download');
        Route::get('/admin/sar/{id}/preview', [TestPasserController::class, 'adminPreviewSar'])->name('admin.sar-preview');
        Route::post('/admin/sar/preview-email-template', [TestPasserController::class, 'previewSarEmailTemplate'])->name('admin.sar-preview-email');
        Route::post('/admin/sar/preview-pdf-template', [TestPasserController::class, 'previewSarPdfTemplate'])->name('admin.sar-preview-pdf');
    });
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/test-passers/upload', [TestPasserController::class, 'upload'])->name('upload');
});

Route::middleware(['auth'])->group(function () {
    Route::put('/test-passers/{test_passer}', [TestPasserController::class, 'update'])->name('test-passers.update');
    Route::post('/test-passers-store', [TestPasserController::class, 'store']);
});

Route::resource('schedules', ScheduleController::class)
    ->names([
        'index' => 'schedules.index',
        'create' => 'schedules.create',
        'store' => 'schedules.store',
        'show' => 'schedules.show',
        'edit' => 'schedules.edit',
        'update' => 'schedules.update',
        'destroy' => 'schedules.destroy',
    ]);

Route::middleware(['auth'])->group(function () {
    Route::get('/user/application', [ConfirmationController::class, 'show']);
    Route::post('/user/application/submit', [ConfirmationController::class, 'submit']);
    Route::post('/user/application/reupload', [ConfirmationController::class, 'reupload']);
    Route::get('/files/{file}/preview', [UserFileController::class, 'preview'])
        ->middleware('signed')
        ->name('files.preview');
    Route::post('/upload-files', [UserFileController::class, 'uploadFiles']);
    Route::post('/get-files', [UserFileController::class, 'getUserApplication']);
});

// Eligible programs - requires authentication
Route::get('/user/eligible-programs', [ConfirmationController::class, 'getEligiblePrograms'])
    ->middleware('auth');

// Evaluator Routes
Route::middleware(['auth', 'role:3'])->group(function () {
    Route::get('/evaluator-dashboard', [EvaluatorDashboardController::class, 'index'])->name('evaluator.dashboard');
    Route::get('/evaluator-applications', function () {
        return Inertia::render('Applications/Evaluator', ['user' => Auth::user()]);
    })->name('evaluator.applications');
    Route::get('/evaluator-dashboard/applicants', [EvaluatorDashboardController::class, 'getUsers']);
    Route::post('/evaluator/pass-application/{userId}', [EvaluatorDashboardController::class, 'passApplication']);
    Route::get('/dashboard/user-files/{id}', [EvaluatorDashboardController::class, 'getUserFiles']);
    Route::post('/dashboard/return-files/{user}', [EvaluatorDashboardController::class, 'returnApplication'])->name('return.files');
});

// Interviewer Routes
Route::middleware(['auth', 'role:4'])->group(function () {
    Route::get('/interviewer-dashboard', [InterviewerDashboardController::class, 'index'])->name('interviewer.dashboard');
    Route::get('/interviewer-applications', function () {
        return Inertia::render('Applications/Interviewer', ['user' => Auth::user()]);
    })->name('interviewer.applications');
    Route::get('/interviewer-dashboard/applicants', [InterviewerDashboardController::class, 'getUsers']);
    Route::get('/interviewer-dashboard/application/{id}', [InterviewerDashboardController::class, 'getUserFiles']);
    Route::post('/interviewer-dashboard/accept/{id}', [InterviewerDashboardController::class, 'accept']);
    Route::post('/interviewer-dashboard/transfer/{id}', [InterviewerDashboardController::class, 'transferToProgram']);
    Route::get('/interviewer-dashboard/programs', [InterviewerDashboardController::class, 'getPrograms']);
});

// Record Staff Routes
Route::middleware(['auth', 'role:6'])->group(function () {
    Route::get('/record-dashboard', [RecordStaffDashboardController::class, 'index'])->name('record.dashboard');
    Route::get('/recordstaff-applications', function () {
        return Inertia::render('Applications/Records', ['user' => Auth::user()]);
    })->name('record.applications');
    Route::get('/record-dashboard/applicants', [RecordStaffDashboardController::class, 'getUsers']);
    Route::get('/record-dashboard/stats', [RecordStaffDashboardController::class, 'getStats']);
    Route::get('/record-dashboard/application/{id}', [RecordStaffDashboardController::class, 'getUserFiles']);
    Route::post('/record-dashboard/tag/{id}', [RecordStaffDashboardController::class, 'tag']);
    Route::post('/record-dashboard/untag/{id}', [RecordStaffDashboardController::class, 'untag']);
    Route::post('/record-dashboard/return-files/{user}', [RecordStaffDashboardController::class, 'returnApplication'])->name('record-return.files');
});

Route::middleware(['auth', 'role:2,3,4,7'])->group(function () {
    Route::get('/dashboard/users', [DashboardController::class, 'getUsers']);
});

Route::middleware(['auth', 'role:2,4,6,7'])->group(function () {
    Route::get('/record-dashboard/programs', [RecordStaffDashboardController::class, 'getPrograms']);
    Route::post('/record-dashboard/change-course/{id}', [RecordStaffDashboardController::class, 'changeCourse']);
});

// User Management Routes (Protected - Admin Only)
Route::middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin-dashboard/user-files/{id}', [DashboardController::class, 'getUserFiles']);
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});

// Audit log routes - Protected by Superadmin middleware
Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/admin/audit-logs/check-new', [AuditLogController::class, 'checkNew'])->name('audit-logs.check-new');
    Route::get('/admin/audit-logs/{id}', [AuditLogController::class, 'show'])->name('audit-logs.show');

    // API Client Management (M2M / Passport)
    Route::get('/admin/api-clients', [\App\Http\Controllers\SuperAdmin\ApiClientController::class, 'index'])->name('api-clients.index');
    Route::post('/admin/api-clients', [\App\Http\Controllers\SuperAdmin\ApiClientController::class, 'store'])->name('api-clients.store');
    Route::delete('/admin/api-clients/{id}', [\App\Http\Controllers\SuperAdmin\ApiClientController::class, 'destroy'])->name('api-clients.destroy');
    Route::post('/admin/api-clients/{id}/regenerate', [\App\Http\Controllers\SuperAdmin\ApiClientController::class, 'regenerate'])->name('api-clients.regenerate');
});

// Callback Routes - Public access for loading screen with API callback
Route::get('/callback', [CallbackController::class, 'index']);
Route::post('/api/callback', [CallbackController::class, 'handle']);
