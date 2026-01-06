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
use App\Http\Controllers\MedicalDashboardController;
use App\Http\Controllers\RecordStaffDashboardController;
use App\Http\Controllers\Admin\Assign\AssignController;


Route::get('/', function () {
    return Inertia::render('Auth/Login', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

// Route::middleware([
//     'auth:sanctum',
//     config('jetstream.auth_session'),
//     'verified',
// ])->get('/dashboard', [DashboardController::class, 'index'])
//   ->name('dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
//Route::get('/dashboard/insights', [DashboardController::class, 'insights']);

Route::get('/admin-dashboard/user-files/{id}', [DashboardController::class, 'getUserFiles']);

Route::post('/check-email', function (\Illuminate\Http\Request $request) {
    $request->validate(['email' => 'required|email']);
    $exists = \App\Models\User::where('email', $request->email)->exists();
    return response()->json(['taken' => $exists]);
});


Route::get('/programs', function () {
    return Inertia::render('Programs');  // This is your Inertia page
})->name('programs.index');


Route::get('/addindex', function () {
    return Inertia::render('AddProgram');  // This is your Inertia page
})->name('programs.addindex');

// ✅ Fetch programs
Route::get('/programs/list', [ProgramController::class, 'index'])->name('programs.list');

// ✅ Create a new program (POST)
Route::post('/programs/store', [ProgramController::class, 'store'])->name('programs.store');

// ✅ Update program slots (PUT)
Route::put('/programs/update/{id}', [ProgramController::class, 'update'])->name('programs.update');

// ✅ Delete a program (DELETE)
Route::delete('/programs/delete/{id}', [ProgramController::class, 'destroy'])->name('programs.delete');

Route::middleware(['auth'])->group(function () {
    Route::get('/applicant-dashboard', [ApplicantDashboardController::class, 'index'])
        ->name('applicant.dashboard');
});

Route::get('/home', function () {
    $roleId = Auth::user()->role_id;

    if ($roleId == 1) {
        return redirect('/applicant-dashboard');
    }

    if ($roleId == 2) {
        return redirect('/dashboard');
    }

    if ($roleId == 3) {
        return redirect('/evaluator-dashboard');
    }

    if ($roleId == 4) {
        return redirect('/interviewer-dashboard');
    }

    if ($roleId == 5) {
        return redirect('/medical-dashboard');
    }

    if ($roleId == 6) {
        return redirect('/record-dashboard');
    }

    // fallback (optional)
    return redirect('/');
})->middleware(['auth'])->name('home');

// routes/web.php (or routes/api.php if you're using API routes)
use App\Http\Controllers\Admin\Notify\Notify;

Route::middleware(['auth'])->post('/test-passers/upload', [Notify::class, 'handleUpload']);
Route::middleware(['auth'])->get('/test-passers/form', [Notify::class, 'showUploadForm'])->name('upload.form');


Route::get('/dashboard-panel', function () {
    return Inertia::render('Dashboards/DashboardPanel');
})->middleware(['auth'])->name('dashboard.panel');

Route::get('/applications', function () {
    return Inertia::render('Applications/Applications');
})->name('applications');

Route::get('/dashboard/users', [DashboardController::class, 'getUsers']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/test-passers', [TestPasserController::class, 'index'])->name('lists');
    Route::post('/test-passers/send-emails', [TestPasserController::class, 'sendEmails']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/test-passers/upload', [TestPasserController::class, 'upload'])->name('upload');
});

Route::put('/test-passers/{id}', [TestPasserController::class, 'update']);

Route::post('/test-passers-store', [TestPasserController::class, 'store']);

// Route::get('/schedules', function () {
//     return Inertia::render('Schedules/Schedule');
// })->name('schedules');

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
});



Route::post('/upload-files', [UserFileController::class, 'uploadFiles']);
Route::post('/get-files', [UserFileController::class, 'getUserApplication']);

Route::middleware(['auth'])->group(function () {
    Route::get('/evaluator-dashboard', [EvaluatorDashboardController::class, 'index'])
        ->name('evaluator.dashboard');
});

Route::get('/evaluator-applications', function () {
    if (Auth::user()?->role_id !== 3) {
         return redirect()->back()->with('error', 'Unauthorized access.');
    }
    return Inertia::render('Applications/EvaluatorApplications', [
    'user' => Auth::user(),
]);

})->name('evaluator.applications'); 

Route::get('/evaluator-dashboard/applicants', [EvaluatorDashboardController::class, 'getUsers']);

Route::post('/evaluator/pass-application/{userId}', [EvaluatorDashboardController::class, 'passApplication']);

Route::get('/dashboard/user-files/{id}', [EvaluatorDashboardController::class, 'getUserFiles']);
Route::post('/dashboard/return-files/{user}', [EvaluatorDashboardController::class, 'returnApplication'])->name('return.files');

Route::get('/test-update-file/{fileId}', function($fileId) {
    $file = \App\Models\UserFile::findOrFail($fileId);
    $file->status = 'returned';
    $file->comment = 'Test note';
    $file->save();
    return $file;
});

Route::middleware(['auth'])->group(function () {
    Route::get('/interviewer-dashboard', [InterviewerDashboardController::class, 'index'])
        ->name('interviewer.dashboard');
});

Route::get('/interviewer-dashboard/applicants', [InterviewerDashboardController::class, 'getUsers']);
Route::get('/interviewer-dashboard/application/{id}', [InterviewerDashboardController::class, 'getUserFiles']);


Route::get('/interviewer-applications', function () {
    if (Auth::user()?->role_id !== 4) {
         return redirect()->back()->with('error', 'Unauthorized access.');
    }
    return Inertia::render('Applications/InterviewerApplication', [
    'user' => Auth::user(),
]);

})->name('interviewer.applications');

Route::post('/interviewer-dashboard/accept/{id}', [InterviewerDashboardController::class, 'accept']);
Route::post('/interviewer-dashboard/transfer/{id}', [InterviewerDashboardController::class, 'transfertoProgram']);
// routes/web.php
Route::get('/interviewer-dashboard/programs', [InterviewerDashboardController::class, 'getPrograms']);
//Route::post('/interviewer-dashboard/transfer/{userId}', [InterviewerDashboardController::class, 'transferToProgram']);

Route::get('/user/eligible-programs', [ConfirmationController::class, 'getEligiblePrograms']);

Route::middleware(['auth'])->group(function () {
    Route::get('/medical-dashboard', [MedicalDashboardController::class, 'index'])
        ->name('medical.dashboard');
});

Route::post('/medical-dashboard/accept/{id}', [MedicalDashboardController::class, 'accept']);

Route::get('/medical-applications', function () {
    if (Auth::user()?->role_id !== 5) {
         return redirect()->back()->with('error', 'Unauthorized access.');
    }
    return Inertia::render('Applications/MedicalApplications', [
    'user' => Auth::user(),
]);

})->name('medical.applications');

Route::get('/medical-dashboard/applicants', [MedicalDashboardController::class, 'getUsers']);
Route::get('/medical-dashboard/application/{id}', [MedicalDashboardController::class, 'getUserFiles']);
Route::post('/medical/return-files/{user}', [MedicalDashboardController::class, 'returnApplication'])->name('medical-return.files');

Route::middleware(['auth'])->group(function () {
    Route::get('/record-dashboard', [RecordStaffDashboardController::class, 'index'])
        ->name('record.dashboard');
});

Route::get('/recordstaff-applications', function () {
    if (Auth::user()?->role_id !== 6) {
         return redirect()->back()->with('error', 'Unauthorized access.');
    }
    return Inertia::render('Applications/RecordStaffApplication', [
    'user' => Auth::user(),
]);

})->name('recordstaff.applications');

Route::post('/record-dashboard/tag/{id}', [RecordStaffDashboardController::class, 'tag']);
Route::post('/record-dashboard/untag/{id}', [RecordStaffDashboardController::class, 'untag']);

use App\Http\Controllers\UserController;

// User Management Routes
Route::get('/legacy/manage-users', [UserController::class, 'index'])->name('users.index');
Route::get('/legacy/users/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
Route::post('/legacy/users/{id}/update', [UserController::class, 'update'])->name('users.update');
Route::delete('/legacy/users/{id}/delete', [UserController::class, 'destroy'])->name('users.destroy');

// show form
Route::get('/legacy/add-user', [UserController::class, 'create'])->name('legacy.add_user');

// handle form submission
Route::post('/legacy/add-user/store', [UserController::class, 'store'])->name('add_user.store');

Route::get('/add-user-vue', function () {
    return Inertia::render('Legacy/AddUser');
})->name('add_user_vue');

//assign interviewer and evaluator
Route::get('/admin/users/create', [AssignController::class, 'createUserForm'])->name('admin.users.create')->middleware('auth');
Route::post('/admin/users/store', [AssignController::class, 'storeUser'])->name('admin.users.store')->middleware('auth');
//actions for assigned users
Route::get('/admin/users/edit/{id}', [AssignController::class, 'editUser'])->name('admin.users.edit');
Route::post('/admin/users/update/{id}', [AssignController::class, 'updateUser'])->name('admin.users.update');
Route::delete('/admin/users/delete/{id}', [AssignController::class, 'deleteUser'])->name('admin.users.delete');

Route::get('/assign-user-vue', function () {
    return Inertia::render('Legacy/Assign');
})->name('assign_user_vue');
