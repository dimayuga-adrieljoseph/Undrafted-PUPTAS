<?php

/**
 * TEMPORARY DEBUG ROUTES - REMOVE AFTER FIXING
 */

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| LOCAL LOGIN BYPASS (APP_DEBUG only)
|--------------------------------------------------------------------------
| Visit /dev-login to see all seeded users and log in as any of them
| without going through the IDP OAuth flow.
|
| Usage:
|   GET /dev-login          → shows a list of users to pick from
|   GET /dev-login?email=X  → logs in directly as user with that email
|   GET /dev-login?id=X     → logs in directly as user with that ID
*/
Route::get('/dev-login', function (\Illuminate\Http\Request $request) {
    if (!config('app.debug')) {
        abort(404);
    }

    // If email or id is provided, log in directly
    $email = $request->query('email');
    $userId = $request->query('id');

    if ($email || $userId) {
        $user = $email
            ? User::where('email', $email)->first()
            : User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        Auth::login($user);

        // Redirect based on role (mirrors IdpAuthController logic)
        $roleId = (int) $user->role_id;
        return match ($roleId) {
            1 => redirect('/applicant-dashboard'),
            3 => redirect('/evaluator-dashboard'),
            4 => redirect('/interviewer-dashboard'),
            6 => redirect('/record-dashboard'),
            default => redirect('/dashboard'),
        };
    }

    // No params → show a simple HTML page listing all users
    $users = User::select('id', 'email', 'firstname', 'lastname', 'role_id')
        ->orderBy('role_id')
        ->get();

    $roleNames = [
        1 => 'Applicant',
        2 => 'Admin',
        3 => 'Evaluator',
        4 => 'Interviewer',
        5 => 'Nurse',
        6 => 'Registrar',
        7 => 'Super Admin',
    ];

    $html = '<html><head><title>Dev Login Bypass</title>'
        . '<style>body{font-family:system-ui,sans-serif;max-width:700px;margin:40px auto;padding:0 20px}'
        . 'h1{color:#1a1a1a}table{width:100%;border-collapse:collapse}th,td{padding:8px 12px;border:1px solid #ddd;text-align:left}'
        . 'a{color:#2563eb;text-decoration:none}a:hover{text-decoration:underline}'
        . '.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:12px;background:#e5e7eb;color:#374151}'
        . '</style></head><body>';
    $html .= '<h1>🔓 Dev Login Bypass</h1>';
    $html .= '<p style="color:#dc2626;font-weight:600">⚠️ DEBUG MODE ONLY — This page is not available in production.</p>';
    $html .= '<table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr></thead><tbody>';

    foreach ($users as $u) {
        $role = $roleNames[$u->role_id] ?? "Unknown ({$u->role_id})";
        $html .= "<tr>"
            . "<td>{$u->id}</td>"
            . "<td>{$u->firstname} {$u->lastname}</td>"
            . "<td>{$u->email}</td>"
            . "<td><span class='badge'>{$role}</span></td>"
            . "<td><a href='/dev-login?id={$u->id}'>Login →</a></td>"
            . "</tr>";
    }

    $html .= '</tbody></table></body></html>';

    return response($html);
})->middleware('web');

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
