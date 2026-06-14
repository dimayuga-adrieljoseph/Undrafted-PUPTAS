<?php

try {
    $request = \App\Http\Requests\StoreCutoffRequest::create('/admin/cutoff-settings', 'POST', ['cutoff_at' => '2026-06-30T12:00']);
    $request->setContainer(app());
    $request->validateResolved();
    
    $controller = app(\App\Http\Controllers\SuperAdmin\CutoffSettingsController::class);
    $controller->store($request);
    
    echo "Success\n";
} catch (\Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
