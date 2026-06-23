<?php
$request = Illuminate\Http\Request::create('/dashboard', 'GET');
$request->headers->set('X-Inertia', 'true');
app('auth')->loginUsingId(1);
$response = app()->handle($request);

dump("Status: " . $response->getStatusCode());
if ($response->headers->has('X-Inertia-Location')) {
    dump("X-Inertia-Location: " . $response->headers->get('X-Inertia-Location'));
}
if ($response->getStatusCode() === 302) {
    dump("Location: " . $response->headers->get('Location'));
}
if(session()->has('errors')) dump("Errors: " . json_encode(session('errors')->getBag('default')->getMessages()));
if(session()->has('error')) dump("Error Flash: " . session('error'));
