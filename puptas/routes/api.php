<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use thiagoalessio\TesseractOCR\TesseractOCR;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ExternalStudentApiController;
use Inertia\Inertia;
use App\Http\Controllers\GradesController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/extract-grades', function (Request $request) {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $file = $request->file('file');

        // Perform OCR with settings optimized for tables
        $ocrText = (new TesseractOCR($file->getPathname()))
            ->lang('eng')
            ->config('tessedit_char_whitelist', '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz| ')
            ->run();

        // Normalize text: Remove special characters and extra spaces
        $ocrText = preg_replace('/[^A-Za-z0-9|\n ]/', '', $ocrText);
        $lines = explode("\n", $ocrText); // Split into lines

        // Initialize final grades
        $grades = [
            'english' => 'N/A',
            'mathematics' => 'N/A',
            'science' => 'N/A',
        ];

        foreach ($lines as $line) {
            $columns = explode('|', $line); // Treat | as column separator

            // Ensure at least 5 columns exist (Final grade is in the 5th column)
            if (count($columns) >= 5) {
                if (stripos($line, 'ENGLISH') !== false) {
                    $grades['english'] = filter_var(trim($columns[4]), FILTER_SANITIZE_NUMBER_INT);
                }
                if (stripos($line, 'MATHEMATICS') !== false) {
                    $grades['mathematics'] = filter_var(trim($columns[4]), FILTER_SANITIZE_NUMBER_INT);
                }
                if (stripos($line, 'SCIENCE') !== false) {
                    $grades['science'] = filter_var(trim($columns[4]), FILTER_SANITIZE_NUMBER_INT);
                }
            }
        }

        return response()->json($grades);
    });

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
