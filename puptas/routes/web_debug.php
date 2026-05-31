<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Dev Login - Bypass IDP for local testing
|--------------------------------------------------------------------------
|
| GET /dev-login         → Shows a page with all seeded users to pick from
| GET /dev-login/{id}    → Logs in as that user and redirects to their dashboard
|
*/
Route::get('/dev-login', function (Request $request) {
    if (!config('app.debug')) {
        abort(404);
    }

    if ($email = $request->query('email')) {
        $user = User::where('email', $email)->first();
        if ($user) {
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
        }

        return response('<h1>User not found for email: ' . e($email) . '</h1>', 404);
    }

    $users = User::with('role')
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

    // Quick-access test applicant accounts
    $testApplicants = [
        ['email' => 'applicant@test.com', 'label' => 'Fresh registrant (no grades)'],
        ['email' => 'applicant2@test.com', 'label' => 'Has grades submitted'],
        ['email' => 'applicant3@test.com', 'label' => 'Application submitted'],
        ['email' => 'applicant4@test.com', 'label' => 'Application accepted & enrolled'],
    ];

    $html = '<html><head><title>Dev Login Bypass</title>'
        . '<style>body{font-family:system-ui,sans-serif;max-width:800px;margin:40px auto;padding:0 20px}'
        . 'h1{color:#1a1a1a}h2{color:#374151;margin-top:32px}table{width:100%;border-collapse:collapse}th,td{padding:8px 12px;border:1px solid #ddd;text-align:left}'
        . 'a{color:#2563eb;text-decoration:none}a:hover{text-decoration:underline}'
        . '.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:12px;background:#e5e7eb;color:#374151}'
        . '.badge-applicant{background:#dbeafe;color:#1e40af}'
        . '.quick-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0}'
        . '.quick-card{border:1px solid #e5e7eb;border-radius:8px;padding:16px;transition:box-shadow .15s}'
        . '.quick-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.08)}'
        . '.quick-card a{font-weight:600;font-size:14px}.quick-card p{margin:4px 0 0;font-size:13px;color:#6b7280}'
        . '</style></head><body>';
    $html .= '<h1>🔓 Dev Login Bypass</h1>';
    $html .= '<p style="color:#dc2626;font-weight:600">⚠️ DEBUG MODE ONLY — This page is not available in production.</p>';

    // Quick-access applicant cards
    $html .= '<h2>🎓 Test Applicants <span style="font-size:13px;color:#6b7280">(Password: Password.1234)</span></h2>';
    $html .= '<div class="quick-grid">';
    foreach ($testApplicants as $ta) {
        $exists = User::where('email', $ta['email'])->exists();
        if ($exists) {
            $html .= "<div class='quick-card'>"
                . "<a href='/dev-login?email={$ta['email']}'>Login as {$ta['email']} →</a>"
                . "<p>{$ta['label']}</p>"
                . "</div>";
        }
    }
    $html .= '</div>';
    if (!User::where('email', 'applicant@test.com')->exists()) {
        $html .= '<p style="color:#9ca3af;font-style:italic">No test applicants found. Run: <code>php artisan db:seed --class=ApplicantSeeder</code></p>';
    }

    $html .= '<h2>All Users</h2>';
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
