<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ExternalStudentApiController;
use App\Http\Controllers\GradesController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/programs/update/{id}', [ProgramController::class, 'update'])->name('programs.update');
    Route::delete('/programs/delete/{id}', [ProgramController::class, 'destroy'])->name('programs.delete');
    Route::post('/store-grades', [GradesController::class, 'store']);
});

use App\Http\Controllers\ExternalProgramApiController;

Route::prefix('v1')
    ->middleware(['external.api.token', 'throttle:external-api-second', 'throttle:external-api-minute', 'throttle:external-api-daily'])
    ->group(function () {
        Route::get('/students', [ExternalStudentApiController::class, 'index']);
        Route::get('/students/idp/{idpUserId}', [ExternalStudentApiController::class, 'showByIdpUserId']);
        Route::get('/students/{studentNumber}', [ExternalStudentApiController::class, 'showByStudentNumber']);
    });

Route::prefix('v1')
    ->middleware(['external.program.api.token', 'throttle:external-api-second', 'throttle:external-api-minute', 'throttle:external-program-api-daily'])
    ->group(function () {
        Route::get('/programs', [ExternalProgramApiController::class, 'index']);
    });

use App\Http\Controllers\ExternalMedicalApiController;

Route::prefix('v1')
    ->middleware(['external.medical.api.token', 'throttle:external-medical-api-second', 'throttle:external-medical-api-minute', 'throttle:external-medical-api-daily'])
    ->group(function () {
        Route::get('/medical/applicants', [ExternalMedicalApiController::class, 'index']);
        Route::get('/medical/applicants/idp/{idpUserId}', [ExternalMedicalApiController::class, 'showByIdpUserId']);
    });

// Route::get('/user-stats', [UserController::class, 'getUserStats']);
// Route::get('/programs', [ProgramController::class, 'index']);
// Route::post('/add-user', [UserController::class, 'store']);
