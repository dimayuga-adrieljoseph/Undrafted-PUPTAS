<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

/**
 * Lightweight replacement for Jetstream's ShareInertiaData middleware.
 *
 * The original calls $user->toArray() which triggers all accessors and
 * lazy-loads relationships (e.g. getStudentNumberAttribute → applicantProfile query)
 * on EVERY request, exhausting Railway's 128MB memory limit.
 *
 * This version shares only the fields the frontend actually needs.
 */
class ShareInertiaData
{
    public function handle($request, $next)
    {
        Inertia::share([
            'jetstream' => function () use ($request) {
                $user = $request->user();

                return [
                    'canCreateTeams'                      => false,
                    'canManageTwoFactorAuthentication'    => Features::canManageTwoFactorAuthentication(),
                    'canUpdatePassword'                   => Features::enabled(Features::updatePasswords()),
                    'canUpdateProfileInformation'         => Features::canUpdateProfileInformation(),
                    'hasEmailVerification'                => Features::enabled(Features::emailVerification()),
                    'flash'                               => $request->session()->get('flash', []),
                    'hasAccountDeletionFeatures'          => Jetstream::hasAccountDeletionFeatures(),
                    'hasApiFeatures'                      => Jetstream::hasApiFeatures(),
                    'hasTeamFeatures'                     => false,
                    'hasTermsAndPrivacyPolicyFeature'     => Jetstream::hasTermsAndPrivacyPolicyFeature(),
                    'managesProfilePhotos'                => Jetstream::managesProfilePhotos(),
                    'two_factor_enabled'                  => $user && Features::enabled(Features::twoFactorAuthentication())
                                                                && ! is_null($user->two_factor_secret),
                ];
            },
            'auth' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return ['user' => null];
                }

                // Only share what the frontend needs — no toArray(), no accessors
                return [
                    'user' => [
                        'id'          => $user->id,
                        'firstname'   => $user->firstname,
                        'lastname'    => $user->lastname,
                        'email'       => $user->email,
                        'role_id'     => $user->role_id,
                        'idp_user_id' => $user->idp_user_id,
                    ],
                ];
            },
            'errorBags' => function () {
                return collect(optional(Session::get('errors'))->getBags() ?: [])
                    ->mapWithKeys(fn ($bag, $key) => [$key => $bag->messages()])
                    ->all();
            },
            'cutoff' => function () use ($request) {
                $service = app(\App\Services\CutoffSettingsService::class);
                $isPassed = $service->isCutoffPassed();
                $hasScoreOverride = false;

                // If cutoff is passed, check if there's an allowed override score for the user
                if ($isPassed) {
                    $email = null;
                    if ($user = $request->user()) {
                        $email = $user->email;
                    } elseif ($pendingReg = $request->session()->get('pending_registration')) {
                        $email = $pendingReg['email'] ?? null;
                    }

                    if ($email) {
                        $testPasser = \App\Models\TestPasser::where('email', $email)->first();
                        if ($testPasser && $service->isScoreAllowed((float) $testPasser->pupcet_total_score)) {
                            $isPassed = false; // Override cutoff for this specific applicant
                            $hasScoreOverride = true;
                        }
                    }
                }

                return [
                    'is_passed' => $isPassed,
                    'display' => $service->formatForDisplay(),
                    'has_score_override' => $hasScoreOverride,
                ];
            },
        ]);

        return $next($request);
    }
}
