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
use App\Http\Controllers\Admin\Assign\AssignController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Admin\Notify\Notify;
use App\Http\Controllers\PrivacyConsentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CallbackController;
use App\Http\Controllers\IdpAuthController;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminOrRegistrar;

Route::get('/', function () {
    return Inertia::render('Auth/Login', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->middleware('guest')->name('welcome');

// IDP Authentication Routes - Public access for OAuth2 login flow
Route::get('/auth/idp/redirect', [IdpAuthController::class, 'login'])
    ->middleware('guest')
    ->name('idp.redirect');

Route::get('/auth/idp/callback', [IdpAuthController::class, 'callback'])
    ->middleware('guest')
    ->name('idp.callback');

Route::post('/auth/idp/logout', [IdpAuthController::class, 'logout'])
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
    Route::post('/interviewer-dashboard/transfer/{id}', [InterviewerDashboardController::class, 'transfertoProgram']);
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

Route::middleware(['auth', 'role:2,4,7'])->group(function () {
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
    Route::get('/admin/users/create', [AssignController::class, 'createUserForm'])->name('admin.users.create');
    Route::post('/admin/users/store', [AssignController::class, 'storeUser'])->name('admin.users.store');
    Route::get('/admin/users/edit/{id}', [AssignController::class, 'editUser'])->name('admin.users.edit');
    Route::post('/admin/users/update/{id}', [AssignController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/admin/users/delete/{id}', [AssignController::class, 'deleteUser'])->name('admin.users.delete');
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
