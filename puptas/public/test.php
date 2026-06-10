<?php
require __DIR__."/../vendor/autoload.php";
$app = require_once __DIR__."/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where("role_id", 3)->first();
if (!$user) {
    die("No user found with role_id 3");
}
Auth::login($user);

// get a user id to test with.
$applicant = App\Models\ApplicantProfile::has('currentApplication')->first();
if (!$applicant) {
    die("No applicant found");
}

$ctrl = app(App\Http\Controllers\EvaluatorDashboardController::class);
try {
    $response = $ctrl->getUserFiles($applicant->user_id);
    if (method_exists($response, 'getData')) {
        echo json_encode($response->getData());
    } else {
        echo json_encode($response);
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
