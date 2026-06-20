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
                3, 8 => '/evaluator-dashboard',
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
        3 => 'Document Evaluator',
        4 => 'Interviewer',
        5 => 'Nurse',
        6 => 'Registrar',
        7 => 'Super Admin',
        8 => 'Grade Evaluator',
    ];

    $roleBadgeColors = [
        1 => ['bg' => '#dbeafe', 'color' => '#1e40af'],
        2 => ['bg' => '#fce7f3', 'color' => '#9d174d'],
        3 => ['bg' => '#d1fae5', 'color' => '#065f46'],
        4 => ['bg' => '#fef3c7', 'color' => '#92400e'],
        5 => ['bg' => '#ede9fe', 'color' => '#5b21b6'],
        6 => ['bg' => '#ffedd5', 'color' => '#9a3412'],
        7 => ['bg' => '#fce7f3', 'color' => '#9E122C'],
        8 => ['bg' => '#d1fae5', 'color' => '#065f46'],
    ];

    // Quick-access test applicant accounts
    $testApplicants = [
        ['email' => 'applicant@test.com',  'label' => 'Fresh registrant (no grades)',        'icon' => 'user'],
        ['email' => 'applicant2@test.com', 'label' => 'Has grades submitted',                'icon' => 'chart'],
        ['email' => 'applicant3@test.com', 'label' => 'Application submitted',               'icon' => 'check'],
        ['email' => 'applicant4@test.com', 'label' => 'Application accepted & enrolled',     'icon' => 'graduation'],
    ];

    // Group users by role
    $grouped = $users->groupBy('role_id');

    ob_start();

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dev Login — PUPTAS</title>
  <style>
    /* ── Reset & base ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; scroll-behavior: smooth; }
    body {
      font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
      background-color: #FDFCF8;
      color: #2C2C24;
      min-height: 100vh;
    }

    /* ── Grain texture overlay ── */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      z-index: 100;
      pointer-events: none;
      opacity: 0.035;
      mix-blend-mode: multiply;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
      background-size: 200px 200px;
    }

    /* ── Ambient blobs ── */
    .blob-top {
      position: fixed; top: -120px; right: -120px;
      width: 400px; height: 400px;
      background: radial-gradient(circle, #9E122C 0%, transparent 70%);
      border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
      filter: blur(60px);
      opacity: 0.12; pointer-events: none; z-index: 0;
    }
    .blob-bottom {
      position: fixed; bottom: -100px; left: -80px;
      width: 360px; height: 360px;
      background: radial-gradient(circle, #C18C5D 0%, transparent 70%);
      border-radius: 40% 60% 70% 30% / 40% 70% 30% 60%;
      filter: blur(50px);
      opacity: 0.10; pointer-events: none; z-index: 0;
    }

    /* ── Navbar ── */
    .nav-wrap {
      position: sticky; top: 16px; z-index: 50;
      margin: 0 16px;
    }
    .nav-inner {
      background: rgba(255,255,255,0.72);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(222,216,207,0.5);
      box-shadow: 0 4px 20px -2px rgba(93,112,82,0.15);
      border-radius: 9999px;
      height: 64px;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 24px;
    }
    .nav-logo {
      display: flex; align-items: center; gap: 12px;
    }
    .nav-logo-circle {
      width: 40px; height: 40px; border-radius: 50%;
      background: #9E122C;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 4px 12px rgba(158,18,44,0.3);
      flex-shrink: 0;
    }
    .nav-logo-circle span { color: #fff; font-weight: 700; font-size: 11px; letter-spacing: -0.03em; }
    .nav-logo-text p:first-child { font-size: 14px; font-weight: 700; color: #9E122C; }
    .nav-logo-text p:last-child  { font-size: 12px; color: #78786C; }
    .nav-badge {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 6px 16px;
      background: rgba(220,38,38,0.07);
      border: 1px solid rgba(220,38,38,0.2);
      border-radius: 9999px;
      font-size: 12px; font-weight: 700; color: #dc2626;
      letter-spacing: 0.05em; text-transform: uppercase;
    }
    .nav-badge::before {
      content: '';
      display: inline-block; width: 8px; height: 8px;
      background: #dc2626; border-radius: 50%;
    }
    .nav-home-btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 20px; border-radius: 9999px;
      background: #9E122C; color: #fff;
      font-size: 13px; font-weight: 700;
      text-decoration: none;
      box-shadow: 0 4px 20px -2px rgba(158,18,44,0.35);
      transition: box-shadow 0.2s, transform 0.2s;
    }
    .nav-home-btn:hover { box-shadow: 0 6px 24px -4px rgba(158,18,44,0.45); transform: scale(1.04); }

    /* ── Page content ── */
    .page-content {
      position: relative; z-index: 10;
      max-width: 900px; margin: 0 auto;
      padding: 48px 20px 80px;
    }

    /* ── Section header ── */
    .section-eyebrow {
      display: inline-block;
      font-size: 11px; font-weight: 700; color: #9E122C;
      text-transform: uppercase; letter-spacing: 0.1em;
      margin-bottom: 6px;
    }
    .section-title {
      font-size: clamp(1.5rem, 3vw, 2rem);
      font-weight: 700; color: #2C2C24;
      margin-bottom: 4px;
    }
    .section-sub {
      font-size: 14px; color: #78786C;
    }
    .section-header { margin-bottom: 20px; }

    /* ── Warning banner ── */
    .warning-banner {
      display: flex; align-items: flex-start; gap: 12px;
      background: rgba(254,242,242,0.85);
      border: 1px solid rgba(252,165,165,0.4);
      border-radius: 16px;
      padding: 16px 20px;
      margin-bottom: 36px;
    }
    .warning-icon {
      width: 36px; height: 36px; border-radius: 10px;
      background: rgba(220,38,38,0.1);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .warning-icon svg { width: 18px; height: 18px; color: #dc2626; }
    .warning-title { font-size: 13px; font-weight: 700; color: #991b1b; }
    .warning-desc  { font-size: 12px; color: #b91c1c; margin-top: 2px; }

    /* ── Quick-access grid ── */
    .quick-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 14px;
      margin-bottom: 48px;
    }
    .quick-card {
      background: #FEFEFA;
      border: 1px solid rgba(222,216,207,0.5);
      border-radius: 20px;
      padding: 20px;
      box-shadow: 0 4px 20px -2px rgba(93,112,82,0.10);
      transition: box-shadow 0.3s, transform 0.3s;
      text-decoration: none;
      display: flex; flex-direction: column; gap: 10px;
    }
    .quick-card:hover {
      box-shadow: 0 12px 32px -8px rgba(93,112,82,0.18);
      transform: translateY(-2px);
    }
    .quick-card-icon {
      width: 44px; height: 44px; border-radius: 14px;
      background: rgba(158,18,44,0.08);
      display: flex; align-items: center; justify-content: center;
      transition: background 0.2s;
    }
    .quick-card:hover .quick-card-icon { background: #9E122C; }
    .quick-card-icon svg { width: 22px; height: 22px; color: #9E122C; transition: color 0.2s; }
    .quick-card:hover .quick-card-icon svg { color: #fff; }
    .quick-card-email {
      font-size: 13px; font-weight: 700; color: #2C2C24;
      word-break: break-word;
    }
    .quick-card-label { font-size: 12px; color: #78786C; }
    .quick-card-arrow {
      margin-top: auto;
      font-size: 12px; font-weight: 600; color: #9E122C;
      display: flex; align-items: center; gap: 4px;
    }
    .no-test-users {
      font-size: 13px; color: #9ca3af; font-style: italic;
      padding: 16px 0;
    }
    .no-test-users code {
      background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px;
      font-style: normal; font-size: 12px;
    }

    /* ── Role groups ── */
    .role-group { margin-bottom: 32px; }
    .role-group-header {
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 12px;
    }
    .role-label {
      display: inline-block;
      padding: 4px 12px; border-radius: 9999px;
      font-size: 12px; font-weight: 700;
    }
    .role-count {
      font-size: 12px; color: #78786C;
    }

    /* ── User cards ── */
    .user-list { display: flex; flex-direction: column; gap: 8px; }
    .user-card {
      display: flex; align-items: center; justify-content: space-between; gap: 12px;
      background: #FEFEFA;
      border: 1px solid rgba(222,216,207,0.5);
      border-radius: 14px;
      padding: 14px 18px;
      box-shadow: 0 2px 8px -2px rgba(93,112,82,0.07);
      transition: box-shadow 0.2s, transform 0.2s;
    }
    .user-card:hover {
      box-shadow: 0 6px 20px -4px rgba(93,112,82,0.14);
      transform: translateX(2px);
    }
    .user-info { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
    .user-name {
      font-size: 14px; font-weight: 600; color: #2C2C24;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .user-email { font-size: 12px; color: #78786C; }
    .user-id { font-size: 11px; color: #a8a8a0; }
    .login-btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 16px; border-radius: 9999px;
      background: #9E122C; color: #fff;
      font-size: 12px; font-weight: 700;
      text-decoration: none;
      box-shadow: 0 2px 8px rgba(158,18,44,0.25);
      transition: box-shadow 0.2s, transform 0.2s;
      white-space: nowrap; flex-shrink: 0;
    }
    .login-btn:hover { box-shadow: 0 4px 16px rgba(158,18,44,0.35); transform: scale(1.05); }
    .login-btn svg { width: 13px; height: 13px; }

    /* ── Divider ── */
    .divider {
      border: none; border-top: 1px solid rgba(222,216,207,0.5);
      margin: 40px 0;
    }

    /* ── Responsive ── */
    @media (max-width: 640px) {
      .nav-logo-text { display: none; }
      .nav-badge     { display: none; }
      .quick-grid    { grid-template-columns: 1fr 1fr; }
      .user-card     { flex-wrap: wrap; }
    }
    @media (max-width: 400px) {
      .quick-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="blob-top"></div>
  <div class="blob-bottom"></div>

  <!-- Navbar -->
  <nav class="nav-wrap">
    <div class="nav-inner">
      <div class="nav-logo">
        <div class="nav-logo-circle"><span>PUP</span></div>
        <div class="nav-logo-text">
          <p>PUPTAS</p>
          <p>Dev Login Bypass</p>
        </div>
      </div>
      <div class="nav-badge">Debug Mode Only</div>
      <a href="/dev-logout-and-redirect" class="nav-home-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px;"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Landing Page
      </a>
    </div>
  </nav>

  <!-- Page content -->
  <div class="page-content">

    <!-- Warning banner -->
    <div class="warning-banner">
      <div class="warning-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div>
        <p class="warning-title">⚠ DEBUG MODE ONLY</p>
        <p class="warning-desc">This page is not available in production. Logging in here bypasses the PUP Identity Provider (IDP).</p>
      </div>
    </div>

    <!-- Quick-access test applicants -->
    <div class="section-header">
      <span class="section-eyebrow">Quick Access</span>
      <h2 class="section-title">Test Applicant Accounts</h2>
      <p class="section-sub">Default password: <strong>Password.1234</strong></p>
    </div>

    <?php
    $hasAnyTestApplicant = false;
    foreach ($testApplicants as $ta) {
        if (User::where('email', $ta['email'])->exists()) {
            $hasAnyTestApplicant = true;
            break;
        }
    }

    $iconSvgs = [
        'user'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'chart'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'check'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        'graduation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>',
    ];

    if ($hasAnyTestApplicant):
    ?>
    <div class="quick-grid">
      <?php foreach ($testApplicants as $ta):
        if (!User::where('email', $ta['email'])->exists()) continue;
        $icon = $iconSvgs[$ta['icon']] ?? $iconSvgs['user'];
      ?>
      <a class="quick-card" href="/dev-login?email=<?= e($ta['email']) ?>">
        <div class="quick-card-icon"><?= $icon ?></div>
        <div>
          <p class="quick-card-email"><?= e($ta['email']) ?></p>
          <p class="quick-card-label"><?= e($ta['label']) ?></p>
        </div>
        <div class="quick-card-arrow">
          Login as this user
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="no-test-users">
      No test applicants found. Run: <code>php artisan db:seed --class=ApplicantSeeder</code>
    </p>
    <?php endif; ?>

    <hr class="divider" />

    <!-- All users grouped by role -->
    <div class="section-header">
      <span class="section-eyebrow">All Seeded Users</span>
      <h2 class="section-title">Login as Any User</h2>
      <p class="section-sub">Grouped by role — click any row to log in instantly.</p>
    </div>

    <?php foreach ($grouped as $roleId => $roleUsers):
      $roleName = $roleNames[$roleId] ?? "Unknown ($roleId)";
      $badge = $roleBadgeColors[$roleId] ?? ['bg' => '#f3f4f6', 'color' => '#374151'];
    ?>
    <div class="role-group">
      <div class="role-group-header">
        <span class="role-label" style="background:<?= e($badge['bg']) ?>;color:<?= e($badge['color']) ?>"><?= e($roleName) ?></span>
        <span class="role-count"><?= $roleUsers->count() ?> user<?= $roleUsers->count() !== 1 ? 's' : '' ?></span>
      </div>
      <div class="user-list">
        <?php foreach ($roleUsers as $u): ?>
        <div class="user-card">
          <div class="user-info">
            <span class="user-name"><?= e($u->firstname . ' ' . $u->lastname) ?></span>
            <span class="user-email"><?= e($u->email) ?></span>
            <span class="user-id">ID: <?= (int) $u->id ?></span>
          </div>
          <a class="login-btn" href="/dev-login/<?= (int) $u->id ?>">
            Login
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

  </div><!-- /page-content -->
</body>
</html>
    <?php

    return response(ob_get_clean());
})->middleware('web');

Route::get('/dev-logout-and-redirect', function (\Illuminate\Http\Request $request) {
    if (!config('app.debug')) {
        abort(404);
    }

    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
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
        3, 8 => '/evaluator-dashboard',
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
