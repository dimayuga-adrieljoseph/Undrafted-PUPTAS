<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\GradesController;
use App\Http\Controllers\ApplicantDashboardController;
use App\Http\Controllers\GradeVerificationSlipController;
use App\Http\Controllers\TestPasserController;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ConfirmationController;
use App\Http\Controllers\UserFileController;
use App\Http\Controllers\EvaluatorDashboardController;
use App\Http\Controllers\StaffProgramController;
use App\Http\Controllers\InterviewerDashboardController;
use App\Http\Controllers\RecordStaffDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Admin\Notify\Notify;
use App\Http\Controllers\PrivacyConsentController;

// Load debug routes (only active when APP_DEBUG=true)
if (file_exists(__DIR__ . '/web_debug.php')) {
    require __DIR__ . '/web_debug.php';
}
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CallbackController;
use App\Http\Controllers\IdpAuthController;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminOrRegistrar;
use App\Http\Controllers\GradeExtractionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AdmissionLogbookController;
use App\Http\Controllers\ControlListController;
use App\Http\Controllers\ConfirmedApplicantsController;
use App\Http\Controllers\EmailTrackingController;


// IDP Authentication Routes - No middleware restrictions so stale sessions don't block the OAuth flow
// Temporary route to create a test applicant without CLI access
Route::get('/setup-test-applicant', function () {
    if (!in_array(config('app.env'), ['local', 'staging'])) {
        abort(404);
    }
    
    $user = \App\Models\User::updateOrCreate(
        ['email' => 'testapplicant@gmail.com'], 
        [
            'password' => bcrypt('password123'), 
            'role_id' => 1, 
            'firstname' => 'Test', 
            'lastname' => 'Applicant', 
            'contactnumber' => '09111111111', 
            'sex' => 'Female', 
            'status' => 'active'
        ]
    );

    \App\Models\ApplicantProfile::updateOrCreate(
        ['user_id' => $user->id],
        [
            'firstname' => 'Test',
            'lastname' => 'Applicant',
            'email' => 'testapplicant@gmail.com',
            'contactnumber' => '09111111111',
            'sex' => 'Female',
            'strand' => 'STEM',
        ]
    );

    return 'Test applicant created successfully! Email: testapplicant@gmail.com | Password: password123. You can now go to /?local=1 and log in.';
});

// Temporary route to create a BRAND NEW applicant without a profile to test the onboarding flow
Route::get('/setup-new-applicant', function () {
    if (!in_array(config('app.env'), ['local', 'staging'])) {
        abort(404);
    }
    
    $user = \App\Models\User::updateOrCreate(
        ['email' => 'newapplicant@gmail.com'], 
        [
            'password' => bcrypt('password123'), 
            'role_id' => 1, 
            'firstname' => 'Fresh', 
            'lastname' => 'Applicant', 
            'contactnumber' => '09999999999', 
            'sex' => 'Male', 
            'status' => 'active'
        ]
    );

    // Ensure they have NO profile so they are forced into the onboarding flow
    \App\Models\ApplicantProfile::where('user_id', $user->id)->delete();

    return 'New applicant created successfully! Email: newapplicant@gmail.com | Password: password123. You can now go to /?local=1 and log in to test the registration/onboarding flow.';
});

// Temporary route to create staff accounts without CLI access
Route::get('/setup-staff', function () {
    if (!in_array(config('app.env'), ['local', 'staging'])) {
        abort(404);
    }
    
    $users = [
        [
            'firstname' => 'System',
            'lastname' => 'Evaluator',
            'contactnumber' => 'N/A',
            'email' => 'evaluator122@gmail.com',
            'password' => bcrypt('Evaluator4321!'),
            'role_id' => 3,
        ],
        [
            'firstname' => 'System',
            'lastname' => 'Interviewer',
            'contactnumber' => 'N/A',
            'email' => 'interviewer133@gmail.com',
            'password' => bcrypt('Interviewer4321!'),
            'role_id' => 4,
        ],
        [
            'firstname' => 'Radianne',
            'lastname' => 'Seguro',
            'contactnumber' => 'N/A',
            'email' => 'seguroradianne@example.com',
            'password' => bcrypt('UGCA4zWe1K7Sfl'),
            'role_id' => 2,
        ],
        [
            'firstname' => 'Mhel',
            'lastname' => 'Garcia',
            'contactnumber' => 'N/A',
            'email' => 'garciamhel@example.com',
            'password' => bcrypt('rKuFYl4jMmTI8&'),
            'role_id' => 6,
        ],
    ];

    foreach ($users as $userData) {
        \App\Models\User::updateOrCreate(
            ['email' => $userData['email']],
            $userData
        );
    }

    return 'Staff accounts created successfully! You can now go to /?local=1 and log in with their credentials.';
});

Route::get('/', function (\Illuminate\Http\Request $request) {
    // Allow bypassing IDP on local and staging using ?local=1
    if (in_array(config('app.env'), ['local', 'staging']) && $request->has('local')) {
        session(['local_bypass' => true]);
        return redirect('/login?local=1');
    }
    
    return Inertia::render('Public/Landing', [
        'appEnv' => config('app.env'),
    ]);
})->name('welcome');

Route::middleware(['idp.maintenance'])->group(function () {
    Route::get('/auth/idp/redirect', [IdpAuthController::class, 'login'])
        ->name('idp.redirect');

    Route::get('/auth/idp/callback', [IdpAuthController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('idp.callback');

    Route::get('/auth/callback', [IdpAuthController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('idp.callback.alias');

    // Backward-compatible callback aliases — kept for IDP redirect_uri compatibility.
    // TODO: Remove these routes once the IDP is updated to use /auth/idp/callback exclusively.
    Route::get('/callback', [IdpAuthController::class, 'callback'])
        ->middleware(['guest', 'throttle:10,1'])
        ->name('idp.callback.legacy');

    Route::get('/api/callback', [IdpAuthController::class, 'callback'])
        ->middleware(['guest', 'throttle:10,1'])
        ->name('idp.callback.api-legacy');
});

Route::get('/auth/idp/error', function () {
    return Inertia::render('Auth/IdpError');
})->name('idp.error');


Route::get('/auth/idp/cancel-registration', [IdpAuthController::class, 'cancelRegistration'])
    ->name('idp.cancel-registration');

Route::post('/api/v1/auth/logout', [IdpAuthController::class, 'logout'])
    ->middleware('auth')
    ->name('idp.logout');


// View applicant details route - expects user ID, restricted to admin, evaluator, and interviewer
Route::get('/applications/user/{user}', function ($user) {
    // Verify user exists and is an applicant
    $applicant = \App\Models\User::where(function($q) use ($user) {
            $q->where('idp_user_id', $user);
            if (is_numeric($user)) {
                $q->orWhere('id', $user);
            }
        })
        ->where('role_id', 1)
        ->whereHas('currentApplication')
        ->first();

    if (!$applicant) {
        abort(404);
    }

    $currentUser = Auth::user();
    $roleId = $currentUser->role_id;

    $context = request('context');

    // Render the role-appropriate component
    $component = match ((int) $roleId) {
        3, 8 => 'Applications/Evaluator',
        4 => 'Applications/Interviewer',
        default => ($context === 'evaluator' && in_array($roleId, [2, 7])) ? 'Applications/Evaluator' : 'Applications/Index',
    };

    $props = [
        'selectedUserId' => (string) $user
    ];

    if ($roleId == 4) {
        $props['assignedPrograms'] = $currentUser->programs()->get(['id', 'code', 'name']);
    }

    return Inertia::render($component, $props);
})->middleware(['auth', 'role:2,3,4,7,8'])->name('applications.show');

Route::post('/check-email', function (\Illuminate\Http\Request $request) {
    $request->validate(['email' => 'required|email']);
    $exists = \App\Models\User::where('email', $request->email)->exists() || 
              \App\Models\TestPasser::where('email', $request->email)->exists();
    return response()->json(['taken' => $exists]);
})->middleware('auth');

Route::post('/check-reference-number', function (\Illuminate\Http\Request $request) {
    $request->validate(['reference_number' => 'required|string|max:100']);
    $testPasser = \App\Models\TestPasser::where('reference_number', trim($request->reference_number))->first();
    
    // Valid if it exists and status is not 3 (Unqualified) or 4 (Waitlisted Below Cutoff)
    $valid = $testPasser && !in_array($testPasser->passer_status_id, [3, 4]);
    
    return response()->json(['valid' => $valid]);
})->middleware('guest');

// Email Link Redirects — hosted on our domain so email link URLs match the sending domain
Route::get('/links/admission-criteria', function () {
    return redirect()->away('https://drive.google.com/file/d/153oJlLhvU9UDjJ5JzFgA04aWurQ_PBbE/view');
})->name('links.admission-criteria');

Route::get('/links/facebook', function () {
    return redirect()->away('https://www.facebook.com/PUPTOFFICIAL');
})->name('links.facebook');

Route::get('/links/register', function () {
    return redirect()->away('https://identity-provider.isaxbsit2027.com/register?client_id=' . config('services.idp.client_id', '037f48dd-245b-450b-9e7a-3348b65b9dad'));
})->name('links.register');

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
    Route::get('/applicant-dashboard/qualified-programs', [ApplicantDashboardController::class, 'getQualifiedPrograms'])
        ->name('applicant.qualified-programs');

    Route::get('/applicant-qualified-programs', [ApplicantDashboardController::class, 'qualifiedProgramsPage'])
        ->name('applicant.qualified-programs.page');

    Route::get('/applicant-profile', [ApplicantDashboardController::class, 'profile'])
        ->name('applicant.profile');

    // Grade Verification Slip — applicant-initiated self-service download
    // Security: uses the authenticated session as the sole data source.
    // No reference number or user ID is accepted as a URL parameter.
    Route::get('/applicant-dashboard/grade-verification-slip', [GradeVerificationSlipController::class, 'download'])
        ->name('applicant.grade-verification-slip');

    Route::middleware(['throttle:grade-extraction'])
        ->post('/api/grades/extract', [GradeExtractionController::class, 'extract']);

    // Grade Input Routes - IMPORTANT: Each strand MUST use its own store method
    // ABM uses storeAbmGrades, ICT uses storeIctGrades, etc.
    Route::get('/grades/abm', [GradesController::class, 'showAbmGradeForm'])->name('grades.abm.form');
    Route::post('/grades/abm', [GradesController::class, 'storeAbmGrades'])->name('grades.abm.store');
    
    Route::get('/grades/ict', [GradesController::class, 'showIctGradeForm'])->name('grades.ict.form');
    Route::post('/grades/ict', [GradesController::class, 'storeIctGrades'])->name('grades.ict.store');
    
    Route::get('/grades/humss', [GradesController::class, 'showHumssGradeForm'])->name('grades.humss.form');
    Route::post('/grades/humss', [GradesController::class, 'storeHumssGrades'])->name('grades.humss.store');
    
    Route::get('/grades/gas', [GradesController::class, 'showGasGradeForm'])->name('grades.gas.form');
    Route::post('/grades/gas', [GradesController::class, 'storeGasGrades'])->name('grades.gas.store');
    
    Route::get('/grades/stem', [GradesController::class, 'showStemGradeForm'])->name('grades.stem.form');
    Route::post('/grades/stem', [GradesController::class, 'storeStemGrades'])->name('grades.stem.store');
    
    Route::get('/grades/tvl', [GradesController::class, 'showTvlGradeForm'])->name('grades.tvl.form');
    Route::post('/grades/tvl', [GradesController::class, 'storeTvlGrades'])->name('grades.tvl.store');

    // Unified grade store — handles all strands including dynamic/additional subjects.
    // The per-strand POST routes above are kept for backward compatibility but the
    // grade input composable (useGradeForm.js) posts here to save dynamic_subjects.
    Route::post('/grades/store', [GradesController::class, 'storeGrades'])->name('grades.store');
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
    if (in_array($roleId, [3, 8])) return redirect('/evaluator-dashboard');
    if ($roleId == 4) return redirect('/interviewer-dashboard');
    if ($roleId == 6) return redirect('/record-dashboard');
    if ($roleId == 7) return redirect('/dashboard');

    return redirect('/');
})->middleware(['auth'])->name('home');

Route::middleware(['auth'])->get('/test-passers/form', [Notify::class, 'showUploadForm'])->name('upload.form');

// Public SAR download with signed URL (expires in 30 days)
Route::get('/sar/download/{reference}/{filename}', [TestPasserController::class, 'downloadSar'])
    ->middleware('signed')
    ->where('filename', '.*')
    ->name('sar.passer-download');

Route::get('/applications', function () {
    return Inertia::render('Applications/Index');
})->middleware(['auth', EnsureAdmin::class])->name('applications');

// Confirmed Applicants Routes (Admin/Registrar only)
Route::middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('/confirmed-applicants', [ConfirmedApplicantsController::class, 'index'])->name('confirmed-applicants.index');
    Route::get('/confirmed-applicants/list', [ConfirmedApplicantsController::class, 'getApplicants'])->name('confirmed-applicants.list');
    Route::post('/confirmed-applicants/send-sar', [ConfirmedApplicantsController::class, 'sendSar'])->name('confirmed-applicants.send-sar');
    Route::post('/confirmed-applicants/send-email', [ConfirmedApplicantsController::class, 'sendCustomEmail'])->name('confirmed-applicants.send-email');
});

// NOTE: These are web (session-based) routes. auth:sanctum was incorrect here —
// Auth::login() uses the 'web' guard, not the Sanctum stateless guard.
Route::middleware(['auth', EnsureAdminOrRegistrar::class])->group(function () {
    Route::get('/test-passers', [TestPasserController::class, 'index'])->name('lists');
    Route::get('/test-passers/select-all-ids', [TestPasserController::class, 'selectAllIds'])->name('test-passers.select-all-ids');
    Route::post('/test-passers/send-emails', [TestPasserController::class, 'sendEmails']);

    Route::get('/admin/sar-generations', [TestPasserController::class, 'getSarGenerations'])->name('admin.sar-generations');
    Route::get('/admin/sar/{id}/download', [TestPasserController::class, 'adminDownloadSar'])->name('admin.sar-download');
    Route::get('/admin/sar/{id}/preview', [TestPasserController::class, 'adminPreviewSar'])->name('admin.sar-preview');
    Route::post('/admin/sar/preview-email-template', [TestPasserController::class, 'previewSarEmailTemplate'])->name('admin.sar-preview-email');
    Route::post('/admin/sar/preview-pdf-template', [TestPasserController::class, 'previewSarPdfTemplate'])->name('admin.sar-preview-pdf');
    Route::post('/admin/waitlisted/preview-email-template', [TestPasserController::class, 'previewWaitlistedEmailTemplate'])->name('admin.waitlisted-preview-email');

    // Email Tracking Routes
    Route::get('/admin/email-tracking', [EmailTrackingController::class, 'index'])->name('email-tracking.index');
    Route::get('/admin/email-tracking/{id}', [EmailTrackingController::class, 'show'])->name('email-tracking.show');
    Route::get('/admin/email-tracking/{id}/progress', [EmailTrackingController::class, 'progress'])->name('email-tracking.progress');
    Route::post('/admin/email-tracking/retry-selected', [EmailTrackingController::class, 'retrySelected'])->name('email-tracking.retry-selected');
    Route::post('/admin/email-tracking/{id}/retry-all', [EmailTrackingController::class, 'retryAll'])->name('email-tracking.retry-all');
});

Route::middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::post('/test-passers/upload', [TestPasserController::class, 'upload'])->name('upload');
});

Route::middleware(['auth', EnsureAdminOrRegistrar::class])->group(function () {
    Route::put('/test-passers/{test_passer}', [TestPasserController::class, 'update'])->name('test-passers.update');
    Route::post('/test-passers-store', [TestPasserController::class, 'store']);
    Route::delete('/test-passers/{test_passer}', [TestPasserController::class, 'destroy'])->name('test-passers.destroy');
    Route::post('/test-passers/bulk-destroy', [TestPasserController::class, 'bulkDestroy'])->name('test-passers.bulk-destroy');
    Route::post('/test-passers/bulk-enroll', [TestPasserController::class, 'bulkEnroll'])->name('test-passers.bulk-enroll');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/user/application', [ConfirmationController::class, 'show']);
    Route::post('/user/application/submit', [ConfirmationController::class, 'submit']);
    Route::post('/user/application/resubmit', [ConfirmationController::class, 'resubmit']);
    Route::post('/user/application/reupload', [ConfirmationController::class, 'reupload']);
    Route::post('/user/application/upload-url', [ConfirmationController::class, 'getUploadUrl']);
    Route::post('/user/application/confirm-upload', [ConfirmationController::class, 'confirmUpload']);
    Route::get('/user/application/file-status', [ConfirmationController::class, 'fileStatus']);
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
Route::middleware(['auth', 'role:2,3,7,8'])->group(function () {
    Route::get('/evaluator-dashboard', [EvaluatorDashboardController::class, 'index'])->name('evaluator.dashboard');
    Route::get('/evaluator-applications', function () {
        return Inertia::render('Applications/Evaluator', ['user' => Auth::user()]);
    })->name('evaluator.applications');
    Route::get('/evaluator-dashboard/applicants', [EvaluatorDashboardController::class, 'getUsers']);
    Route::post('/evaluator/pass-application/{userId}', [EvaluatorDashboardController::class, 'passApplication']);
    Route::post('/evaluator/start-review/{applicationProcess}', [EvaluatorDashboardController::class, 'startReview']);
    Route::post('/evaluator/reject-application/{userId}', [EvaluatorDashboardController::class, 'rejectApplication']);
    Route::post('/evaluator/flag-application/{userId}', [EvaluatorDashboardController::class, 'flagApplication']);
    Route::get('/dashboard/user-files/{id}', [EvaluatorDashboardController::class, 'getUserFiles']);
    Route::post('/dashboard/return-files/{user}', [EvaluatorDashboardController::class, 'returnApplication'])->name('return.files');
    Route::get('/evaluator-programs', [StaffProgramController::class, 'index'])->name('evaluator.programs');
});

// Interviewer Routes
Route::middleware(['auth', 'role:4'])->group(function () {
    Route::get('/interviewer-dashboard', [InterviewerDashboardController::class, 'index'])->name('interviewer.dashboard');
    Route::get('/interviewer-applications', function () {
        $user = Auth::user();
        $assignedPrograms = $user->programs()->get(['programs.id', 'programs.code', 'programs.name', 'programs.slots']);
        return Inertia::render('Applications/Interviewer', [
            'user' => $user,
            'assignedPrograms' => $assignedPrograms,
        ]);
    })->name('interviewer.applications');
    Route::get('/interviewer-dashboard/applicants', [InterviewerDashboardController::class, 'getUsers']);
    Route::get('/interviewer-dashboard/application/{id}', [InterviewerDashboardController::class, 'getUserFiles']);
    Route::post('/interviewer-dashboard/start/{id}', [InterviewerDashboardController::class, 'start']);
    Route::post('/interviewer-dashboard/accept/{id}', [InterviewerDashboardController::class, 'accept']);
    Route::post('/interviewer-dashboard/reject/{id}', [InterviewerDashboardController::class, 'reject']);
    Route::get('/interviewer-programs', [StaffProgramController::class, 'index'])->name('interviewer.programs');
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
    Route::get('/record-programs', [StaffProgramController::class, 'index'])->name('record.programs');
});

Route::middleware(['auth', 'role:2,3,4,6,8'])->group(function () {
    Route::get('/api/lazy-load/document/{userId}/{fileType}', [\App\Http\Controllers\LazyLoadController::class, 'loadDocument']);
    Route::post('/api/lazy-load/documents-batch/{userId}', [\App\Http\Controllers\LazyLoadController::class, 'loadDocumentsBatch']);
    Route::get('/api/lazy-load/grades/{userId}', [\App\Http\Controllers\LazyLoadController::class, 'loadGrades']);
});

// Add grades endpoint to each role's trait-based controllers
Route::middleware(['auth', 'role:2,3,7,8'])->group(function () {
    Route::get('/dashboard/user-grades/{id}', [EvaluatorDashboardController::class, 'getUserGrades']);
});

// Common Staff Routes
Route::middleware(['auth', 'role:2,3,4,6,7,8'])->group(function () {
    Route::get('/api/staff/programs/slots', [StaffProgramController::class, 'getPrograms']);
});

Route::middleware(['auth', 'role:4'])->group(function () {
    Route::get('/interviewer-dashboard/user-grades/{id}', [InterviewerDashboardController::class, 'getUserGrades']);
});

Route::middleware(['auth', 'role:6'])->group(function () {
    Route::get('/record-dashboard/user-grades/{id}', [RecordStaffDashboardController::class, 'getUserGrades']);
});

Route::middleware(['auth', 'role:2,7'])->group(function () {
    Route::get('/admin-dashboard/user-grades/{id}', [DashboardController::class, 'getUserGrades']);
});

Route::middleware(['auth', 'role:2,3,4,7,8'])->group(function () {
    Route::get('/dashboard/users', [DashboardController::class, 'getUsers']);
});

Route::middleware(['auth', 'role:2,4,6,7'])->group(function () {
    Route::get('/record-dashboard/programs', [RecordStaffDashboardController::class, 'getPrograms']);
});

Route::middleware(['auth', 'role:2,4,7'])
    ->post('/record-dashboard/change-course/{applicantId}', [RecordStaffDashboardController::class, 'changeCourse'])
    ->name('record.change-course');

// User Management Routes (Protected - Admin Only)
Route::middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin-dashboard/user-files/{id}', [DashboardController::class, 'getUserFiles']);
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/search', [UserController::class, 'search'])->name('users.search');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::put('/users/{user}/grades', [UserController::class, 'updateGrades'])->name('users.grades.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Admin Reports
    Route::get('/admin/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/admin/reports/data', [ReportController::class, 'getReportData'])->name('reports.data');
    Route::get('/admin/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('/admin/reports/export/excel', [ReportController::class, 'exportExcel'])->name('reports.export.excel');

    // Logbook Reports
    Route::get('/admin/logbook', [AdmissionLogbookController::class, 'index'])->name('reports.logbook.index');
    Route::get('/admin/logbook/export/pdf', [AdmissionLogbookController::class, 'exportPdf'])->name('reports.logbook.export.pdf');
    Route::get('/admin/control-list', [ControlListController::class, 'index'])->name('reports.control-list.index');
    Route::get('/admin/control-list/export', [ControlListController::class, 'export'])->name('reports.control-list.export');
});

// Audit log routes - Protected by Superadmin middleware
Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/admin/audit-logs/check-new', [AuditLogController::class, 'checkNew'])->name('audit-logs.check-new');
    Route::post('/admin/audit-logs/analyze', [AuditLogController::class, 'analyze'])->name('audit-logs.analyze');
    Route::get('/admin/audit-logs/analytics-history', [AuditLogController::class, 'getHistory'])->name('audit-logs.history');
    Route::delete('/admin/audit-logs/analytics-history/{id}', [AuditLogController::class, 'deleteHistory'])->name('audit-logs.history.delete');
    Route::get('/admin/audit-logs/{id}', [AuditLogController::class, 'show'])->name('audit-logs.show');

    // API Client Management (M2M / Passport)
    Route::get('/admin/api-clients', [\App\Http\Controllers\SuperAdmin\ApiClientController::class, 'index'])->name('api-clients.index');
    Route::post('/admin/api-clients', [\App\Http\Controllers\SuperAdmin\ApiClientController::class, 'store'])->name('api-clients.store');
    Route::delete('/admin/api-clients/{id}', [\App\Http\Controllers\SuperAdmin\ApiClientController::class, 'destroy'])->name('api-clients.destroy');
    Route::post('/admin/api-clients/{id}/regenerate', [\App\Http\Controllers\SuperAdmin\ApiClientController::class, 'regenerate'])->name('api-clients.regenerate');

    // Cutoff Settings
    Route::get('/admin/cutoff-settings', [\App\Http\Controllers\SuperAdmin\CutoffSettingsController::class, 'index'])->name('cutoff-settings.index');
    Route::post('/admin/cutoff-settings', [\App\Http\Controllers\SuperAdmin\CutoffSettingsController::class, 'store'])->name('cutoff-settings.store');
    Route::delete('/admin/cutoff-settings', [\App\Http\Controllers\SuperAdmin\CutoffSettingsController::class, 'destroy'])->name('cutoff-settings.destroy');
});

// Temporary debug route for SAR PDF generation
Route::get('/debug-sar-error', function () {
    try {
        $sarService = app(\App\Services\SarFormService::class);
        $result = $sarService->generateSarPdf([
            'reference_number' => 'DEBUG-TEST-001',
            'full_name' => 'DOE, JOHN SMITH',
            'graduation_year' => '2026',
            'school_attended' => 'Test High School',
            'shs_strand' => 'STEM',
            'enrollment_date' => date('Y-m-d'),
            'enrollment_time' => date('H:i'),
        ]);
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Callback Routes - Public access for loading screen
Route::get('/callback-loading', [CallbackController::class, 'index']);

// Public Admission Status Checker - No auth required
Route::get('/admission-results', fn () => Inertia::render('Public/CheckStatus'))->name('public.check-status');