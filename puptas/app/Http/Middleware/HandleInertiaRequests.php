<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Auth;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'csrf_token' => $request->session()->token(),
            'flash' => [
                'error' => $request->session()->get('error'),
                'success' => $request->session()->get('success'),
                'status' => $request->session()->get('status'),
                'new_client' => $request->session()->get('new_client'),
            ],
            'auth' => [
                'user' => $request->user() ? [
                    'id'         => $request->user()->id,
                    'firstname'  => $request->user()->firstname,
                    'lastname'   => $request->user()->lastname,
                    'email'      => $request->user()->email,
                    'role_id'    => $request->user()->role_id,
                    // Intentionally excluding idp_user_id and tokens from frontend payload
                ] : null,
            ],
            // Strip server-only token fields before sending to frontend.
            // access_token and refresh_token are only needed server-side (cancelRegistration);
            // exposing them in the Inertia page payload would make them visible in DevTools.
            'pending_registration' => (function () use ($request) {
                $reg = $request->session()->get('pending_registration');
                if (!$reg) return null;
                return array_diff_key($reg, array_flip(['access_token', 'refresh_token']));
            })(),
            'test_passer_data' => function () use ($request) {
                $pendingReg = $request->session()->get('pending_registration');
                if ($pendingReg && !empty($pendingReg['email'])) {
                    return \App\Models\TestPasser::where('email', $pendingReg['email'])->first();
                }
                return null;
            },
            'privacy_consent' => [
                'required' => $request->user() ? !$request->user()->privacy_consent : false,
            ],
            'appEnv' => config('app.env'),
        ]);
    }
}
