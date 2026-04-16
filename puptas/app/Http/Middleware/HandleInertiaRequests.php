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
                'new_client' => $request->session()->get('new_client'),
            ],
            'auth' => [
                'user' => $request->user() ? [
                    'id'         => $request->user()->id,
                    'firstname'  => $request->user()->firstname,
                    'lastname'   => $request->user()->lastname,
                    'email'      => $request->user()->email,
                    'role_id'    => $request->user()->role_id,
                    'idp_user_id' => $request->user()->idp_user_id,
                ] : null,
            ],
            'pending_registration' => $request->session()->get('pending_registration'),
            'privacy_consent' => [
                'required' => $request->user() ? !$request->user()->privacy_consent : false,
            ],
        ]);
    }
}
