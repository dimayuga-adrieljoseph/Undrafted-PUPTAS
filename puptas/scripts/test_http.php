<?php

$request = \Illuminate\Http\Request::create('/admin/cutoff-settings', 'POST', ['cutoff_at' => '']);
$request->headers->set('X-Inertia', 'true');
$response = app()->handle($request);
echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Content:\n" . $response->getContent() . "\n";
