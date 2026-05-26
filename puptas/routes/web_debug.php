<?php

/**
 * TEMPORARY DEBUG ROUTES - REMOVE AFTER FIXING
 */

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Test email delivery directly (bypasses queue).
 * Access: /debug-email?to=your-email@gmail.com
 */
Route::get('/debug-email', function (\Illuminate\Http\Request $request) {
    $to = $request->query('to');
    if (!$to) {
        return response()->json(['error' => 'Provide ?to=email@example.com'], 400);
    }

    // Clear config cache to pick up new env vars
    \Illuminate\Support\Facades\Artisan::call('config:clear');

    try {
        $result = Mail::raw('This is a test email from PUPTAS at ' . now()->toIso8601String(), function ($msg) use ($to) {
            $msg->to($to)->subject('PUPTAS Email Delivery Test');
        });

        return response()->json([
            'status' => 'sent',
            'to' => $to,
            'mailer' => config('mail.default'),
            'from' => config('mail.from.address'),
            'resend_key_prefix' => substr(config('services.resend.key') ?? env('RESEND_API_KEY'), 0, 10) . '...',
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'class' => get_class($e),
            'mailer' => config('mail.default'),
            'from' => config('mail.from.address'),
            'resend_key_prefix' => substr(config('services.resend.key') ?? env('RESEND_API_KEY'), 0, 10) . '...',
        ], 500);
    }
})->middleware('web');

Route::get('/debug-registration', function (\Illuminate\Http\Request $request) {
    try {
        // Allow access even in production for debugging
        $email = $request->query('email');
        
        $diagnostics = [
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug'),
        ];
        
        // If email provided, check test passer
        if ($email) {
            try {
                $testPasser = DB::table('test_passers')
                    ->where('email', $email)
                    ->first();
                
                $diagnostics['test_passer'] = $testPasser ? [
                    'found' => true,
                    'reference_number' => $testPasser->reference_number ?? 'NULL',
                    'email' => $testPasser->email,
                    'status' => $testPasser->status ?? 'NULL',
                    'user_id' => $testPasser->user_id ?? 'NULL',
                    'batch_number' => $testPasser->batch_number ?? 'NULL',
                    'school_year' => $testPasser->school_year ?? 'NULL',
                    'passer_status_id' => $testPasser->passer_status_id ?? 'NULL',
                    'year_graduated' => $testPasser->year_graduated ?? 'NULL',
                ] : ['found' => false];
                
                // Check if user already exists
                $existingUser = DB::table('users')->where('email', $email)->first();
                $diagnostics['existing_user'] = $existingUser ? [
                    'found' => true,
                    'id' => $existingUser->id,
                ] : ['found' => false];
                
            } catch (\Exception $e) {
                $diagnostics['test_passer_error'] = $e->getMessage();
            }
        } else {
            $diagnostics['message'] = 'Provide ?email=xxx to check test passer data';
        }
        
        // Check graduate types exist
        try {
            $graduateTypes = DB::table('graduate_types')->pluck('label')->toArray();
            $diagnostics['graduate_types_count'] = count($graduateTypes);
            $diagnostics['graduate_types'] = $graduateTypes;
        } catch (\Exception $e) {
            $diagnostics['graduate_types_error'] = $e->getMessage();
        }
        
        return response()->json($diagnostics, 200, [], JSON_PRETTY_PRINT);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500, [], JSON_PRETTY_PRINT);
    }
})->middleware('web');
