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
        // IDP Bypass: If no pending registration, proceed as local user.
        // if (!$pendingReg) {
        //     abort(403, 'You must login via the IDP first.');
        // }

        $rules = [
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
            'schoolyear' => ['required', 'string', 'exists:graduate_types,label'],
        ];

        // Conditional validation for local registration (non-IDP)
        if (!$pendingReg) {
            $rules['email'] = ['required', 'string', 'email', 'max:255', 'unique:users'];
            $rules['password'] = $this->passwordRules();
        }

        Validator::make($input, $rules, [
            'email.unique' => 'This email is already registered.',
        ])->validate();

        return DB::transaction(function () use ($input, $pendingReg) {
            // First, create the local User record
            $user = User::create([
                'idp_user_id' => $pendingReg['user_id'] ?? null,
                'email' => $pendingReg['email'] ?? ($input['email'] ?? null),
                'role_id' => 1,
                'firstname' => $input['firstname'],
                'lastname' => $input['lastname'],
                'middlename' => $input['middlename'] ?? null,
                'birthday' => $input['birthday'],
                'sex' => $input['sex'],
                'contactnumber' => $input['contactnumber'],
                'street_address' => $input['street_address'],
                'barangay' => $input['barangay'],
                'city' => $input['city'],
                'province' => $input['province'],
                'postal_code' => $input['postal_code'] ?? null,
                'password' => Hash::make($input['password']), // Use the password provided by the user
                'privacy_consent' => true,
                'privacy_consent_at' => now(),
            ]);

            // Create applicant profile serving as the primary demographic record
            $profile = ApplicantProfile::create([
                'user_id' => $user->id, // Map exactly to local User ID
                'email' => $user->email,
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
                // Keep traditional school fields if they exist
                'school' => $input['school'] ?? null,
                'school_address' => $input['schoolAdd'] ?? null,
                'date_graduated' => $input['dateGrad'] ?? null,
                'strand' => $input['strand'] ?? null,
                'track' => $input['track'] ?? null,
            ]);

            // Attach graduate type via junction table
            if (!empty($input['schoolyear'])) {
                $graduateType = \App\Models\GraduateType::where('label', $input['schoolyear'])->first();
                if ($graduateType) {
                    $profile->graduateTypes()->sync([$graduateType->id]);
                }
            }

            if (!empty($pendingReg['access_token'])) {
                \App\Models\RefreshToken::create([
                    'user_id'       => $user->id,
                    'access_token'  => $pendingReg['access_token'],
                    'refresh_token' => $pendingReg['refresh_token'] ?? null,
                    'expires_at'    => $pendingReg['expires_at'] ?? now()->addHour(),
                ]);

                \Illuminate\Support\Facades\Cookie::queue('access_token', $pendingReg['access_token'], 60, null, null, false, false);
                if (!empty($pendingReg['refresh_token'])) {
                    \Illuminate\Support\Facades\Cookie::queue('refresh_token', $pendingReg['refresh_token'], 60*24*30, null, null, false, false);
                }
            }

            // Clear the pending registration from session
            session()->forget('pending_registration');

            // Log them in using our standard Eloquent User
            Auth::login($user);

            return $user;
        });
    }

    // Teams management is not needed for IDP string users
}
