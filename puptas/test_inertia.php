<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/register?email=makuippo888@gmail.com', 'GET');
$request->setLaravelSession(app('session')->driver('array'));
app()->instance('request', $request);

$service = app(\App\Services\CutoffSettingsService::class);
$service->addAllowedRegistrationScore(85.50);
\App\Models\TestPasser::firstOrCreate(
    ['email' => 'makuippo888@gmail.com'],
    [
        'reference_number' => 'TEST-001',
        'first_name' => 'Test',
        'surname' => 'User',
        'passer_status_id' => 4,
        'pupcet_total_score' => 85.50
    ]
);

$middleware = app(\App\Http\Middleware\ShareInertiaData::class);
$middleware->handle($request, function ($req) {
    return \Inertia\Inertia::render('Auth/Register');
});

$shared = \Inertia\Inertia::getShared();
$cutoffClosure = $shared['cutoff'] ?? null;
if (is_callable($cutoffClosure)) {
    $cutoff = $cutoffClosure();
    echo "Cutoff Output:\n";
    echo json_encode($cutoff, JSON_PRETTY_PRINT);
} else {
    echo "Cutoff is not a closure: " . json_encode($cutoffClosure);
}
