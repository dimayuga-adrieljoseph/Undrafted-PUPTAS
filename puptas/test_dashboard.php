<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

Illuminate\Support\Facades\Auth::loginUsingId(1);
$request = Illuminate\Http\Request::create('/dashboard', 'GET');
$request->headers->set('X-Inertia', 'true');
$response = $kernel->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() == 302) {
    echo "Location: " . $response->headers->get('Location') . "\n";
    if (session()->has('errors')) {
        echo "Errors: " . json_encode(session('errors')->getBag('default')->getMessages()) . "\n";
    }
} else if ($response->getStatusCode() == 500) {
    echo "Content: " . $response->getContent() . "\n";
} else {
    echo "Content Length: " . strlen($response->getContent()) . "\n";
}
