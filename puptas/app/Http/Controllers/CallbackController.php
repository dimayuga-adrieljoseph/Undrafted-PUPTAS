<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class CallbackController extends Controller
{
    /**
     * Display the callback loading page.
     * 
     * This page is used as an intermediary page that shows a loading screen
     * while making an API call. Once the API responds, it redirects to the
     * appropriate page.
     */
    public function index(Request $request)
    {
        // Get the API endpoint from query string, or use default
        $apiEndpoint = $request->get('api', '/api/callback');
        
        // Get redirect URLs
        $redirectTo = $request->get('redirect', '/dashboard');
        $errorRedirect = $request->get('error', '/login');

        return Inertia::render('Callback', [
            'apiEndpoint' => $apiEndpoint,
            'redirectTo' => $redirectTo,
            'errorRedirect' => $errorRedirect,
        ]);
    }

    /**
     * Handle the actual API callback.
     * 
     * This is the endpoint that will be called from the frontend.
     * Connect your API logic here.
     */
    public function handle(Request $request)
    {
        // TODO: Connect your API logic here
        // Example:
        // $response = Http::post($request->input('api_url'), $request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'Callback processed successfully',
            'data' => $request->all(),
        ]);
    }
}
