<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Dev Login - Bypass IDP for local testing
|--------------------------------------------------------------------------
|
| GET /dev-login         → Shows a page with all seeded users to pick from
| GET /dev-login/{id}    → Logs in as that user and redirects to their dashboard
|
*/
Route::get('/dev-login', function () {
    if (!config('app.debug')) {
        abort(404);
    }

    $users = \App\Models\User::with('role')
        ->orderBy('role_id')
        ->get(['id', 'email', 'firstname', 'lastname', 'role_id']);

    $html = '<html><head><title>Dev Login</title>'
        . '<style>body{font-family:system-ui;max-width:600px;margin:40px auto;padding:0 20px}'
        . 'a{display:block;padding:12px 16px;margin:8px 0;background:#f3f4f6;border-radius:8px;text-decoration:none;color:#111}'
        . 'a:hover{background:#e5e7eb}.role{color:#6b7280;font-size:0.85em}</style></head>'
        . '<body><h1>🔓 Dev Login</h1><p>Pick a user to log in as:</p>';

    foreach ($users as $user) {
        $roleName = $user->role->name ?? "Role {$user->role_id}";
        $name = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')) ?: $user->email;
        $html .= "<a href=\"/dev-login/{$user->id}\"><strong>{$name}</strong><br>"
            . "<span class=\"role\">{$user->email} — {$roleName}</span></a>";
    }

    $html .= '</body></html>';
    return response($html);
})->middleware('web');

Route::get('/dev-login/{id}', function ($id) {
    if (!config('app.debug')) {
        abort(404);
    }

    $user = \App\Models\User::findOrFail($id);
    Auth::login($user);

    $redirect = match ((int) $user->role_id) {
        1 => '/applicant-dashboard',
        2, 7 => '/dashboard',
        3 => '/evaluator-dashboard',
        4 => '/interviewer-dashboard',
        6 => '/record-dashboard',
        default => '/dashboard',
    };

    return redirect($redirect);
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
