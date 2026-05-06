<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

// Grab a program code to test with
$programs = \App\Models\Program::take(2)->get();
$programCodes = $programs->pluck('code')->toArray();

// Create the request
$request = new \Illuminate\Http\Request([
    'firstname' => 'TestFirst',
    'lastname' => 'TestLast',
    'middlename' => 'TestMid',
    'extension_name' => 'Jr.',
    'email' => 'superadmin@puptas.edu',
    'contactnumber' => '1234567890',
    'role_id' => 3, // Evaluator
    'program' => $programCodes,
]);

// Attempt to invoke the controller
$ctrl = new \App\Http\Controllers\UserController(app(\App\Services\UserService::class), app(\App\Services\AuditLogService::class));
$user = \App\Models\User::where('email', 'superadmin@puptas.edu')->first();
$id = $user->idp_user_id;

$ctrl->update($request, $id);

// Check assigned programs
$user = \App\Models\User::with('programs')->where('email', 'superadmin@puptas.edu')->first();
echo json_encode([
    'role_id' => $user->role_id,
    'assigned_programs' => $user->programs->pluck('code')->toArray(),
]);
