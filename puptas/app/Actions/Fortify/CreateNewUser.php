<?php

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use App\Models\ApplicantProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Illuminate\Support\Facades\Auth;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input)
    {
        $pendingReg = session('pending_registration');
        if (!$pendingReg) {
            abort(403, 'You must login via the IDP first.');
        }

        Validator::make($input, [
            'lastname' => ['required', 'string', 'max:255'],
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'birthday' => ['required', 'date'],
            'sex' => ['required', 'string'],
            'contactnumber' => ['required', 'string', 'max:15'],
            'street_address' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            // Email uniqueness now checked against applicant_profiles
            // Note: If you want email editable, ensure it comes from $input. Otherwise use the IDP email.
        ])->validate();

        return DB::transaction(function () use ($input, $pendingReg) {
            // Create applicant profile serving as the primary demographic record
            $profile = ApplicantProfile::create([
                'user_id' => $pendingReg['user_id'], // Map exactly to IDP UUID
                'email' => $pendingReg['email'] ?? ($input['email'] ?? null),
                'firstname' => $input['firstname'],
                'middlename' => $input['middlename'] ?? null,
                'lastname' => $input['lastname'],
                'birthday' => $input['birthday'],
                'sex' => $input['sex'],
                'contactnumber' => $input['contactnumber'],
                'street_address' => $input['street_address'],
                'barangay' => $input['barangay'],
                'city' => $input['city'],
                'province' => $input['province'],
                'postal_code' => $input['postal_code'] ?? null,
                'privacy_consent' => true,
                'privacy_consent_at' => now(),
                // Keep traditional school fields if they exist
                'school' => $input['school'] ?? null,
                'school_address' => $input['schoolAdd'] ?? null,
                'school_year' => $input['schoolyear'] ?? null,
                'date_graduated' => $input['dateGrad'] ?? null,
                'strand' => $input['strand'] ?? null,
                'track' => $input['track'] ?? null,
            ]);

            // Clear the pending registration from session
            session()->forget('pending_registration');

            // Build the standard IDP user array
            $idpUserProfile = [
                'idp_user_id' => $pendingReg['user_id'],
                'name'        => ($input['firstname'] . ' ' . $input['lastname']) ?? 'IDP User',
                'email'       => $profile->email,
                'role_id'     => 1,
                'role_name'   => 'applicant',
            ];

            // Store in session so IdpUserProvider can recreate it on next requests
            session(['idp_user_profile' => $idpUserProfile]);

            // Log them in using our virtual user class
            $localUser = new \App\Auth\IdpUser($idpUserProfile);
            Auth::login($localUser);

            return $localUser;
        });
    }

    // Teams management is not needed for IDP string users
}
