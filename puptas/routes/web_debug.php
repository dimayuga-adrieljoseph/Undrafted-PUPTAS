<?php

/**
 * TEMPORARY DEBUG ROUTE - REMOVE AFTER FIXING REGISTRATION
 * 
 * This route helps diagnose registration issues by checking:
 * 1. Database tables exist
 * 2. Test passer data is correct
 * 3. Graduate types exist
 * 
 * Access: /debug-registration?email=test@example.com
 */

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

Route::get('/debug-registration', function (\Illuminate\Http\Request $request) {
    // Only allow in non-production or with debug enabled
    if (!config('app.debug')) {
        abort(404);
    }
    
    $email = $request->query('email');
    
    $diagnostics = [
        'timestamp' => now()->toIso8601String(),
        'environment' => config('app.env'),
        'debug_mode' => config('app.debug'),
        'database_connection' => config('database.default'),
    ];
    
    // Check required tables exist
    $requiredTables = [
        'users',
        'applicant_profiles',
        'test_passers',
        'graduate_types',
        'applicant_profile_graduate_type',
        'refresh_tokens',
    ];
    
    $diagnostics['tables'] = [];
    foreach ($requiredTables as $table) {
        $diagnostics['tables'][$table] = Schema::hasTable($table);
    }
    
    // Check users table columns
    if (Schema::hasTable('users')) {
        $diagnostics['users_columns'] = Schema::getColumnListing('users');
    }
    
    // Check graduate types exist
    try {
        $graduateTypes = DB::table('graduate_types')->pluck('label')->toArray();
        $diagnostics['graduate_types'] = $graduateTypes;
    } catch (\Exception $e) {
        $diagnostics['graduate_types_error'] = $e->getMessage();
    }
    
    // If email provided, check test passer
    if ($email) {
        try {
            $testPasser = DB::table('test_passers')
                ->where('email', $email)
                ->first();
            
            $diagnostics['test_passer'] = $testPasser ? [
                'found' => true,
                'reference_number' => $testPasser->reference_number ?? null,
                'email' => $testPasser->email,
                'status' => $testPasser->status ?? null,
                'user_id' => $testPasser->user_id ?? null,
                'batch_number' => $testPasser->batch_number ?? null,  // Check if null
                'school_year' => $testPasser->school_year ?? null,    // Check if null
                'passer_status_id' => $testPasser->passer_status_id ?? null,  // Check if null
                'year_graduated' => $testPasser->year_graduated ?? null,
            ] : ['found' => false];
            
            // Check if user already exists
            $existingUser = DB::table('users')->where('email', $email)->first();
            $diagnostics['existing_user'] = $existingUser ? [
                'found' => true,
                'id' => $existingUser->id,
                'email' => $existingUser->email,
            ] : ['found' => false];
            
        } catch (\Exception $e) {
            $diagnostics['test_passer_error'] = $e->getMessage();
        }
    }
    
    // Check session data (if available)
    if (session()->has('pending_registration')) {
        $pendingReg = session('pending_registration');
        $diagnostics['pending_registration'] = [
            'has_session' => true,
            'email' => $pendingReg['email'] ?? null,
            'has_access_token' => !empty($pendingReg['access_token']),
            'has_user_id' => !empty($pendingReg['user_id']),
        ];
    } else {
        $diagnostics['pending_registration'] = ['has_session' => false];
    }
    
    return response()->json($diagnostics, 200, [], JSON_PRETTY_PRINT);
})->middleware('web');
