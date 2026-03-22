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
    public function create(array $input): User
    {
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
            'school' => ['required', 'string', 'max:255'],
            'schoolAdd' => ['required', 'string', 'max:255'],
            'schoolyear' => ['required', 'string', 'max:50'],
            'dateGrad' => ['required', 'date'],
            'strand' => ['required', 'string', 'max:50'],
            'track' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'], // Removed unique:users to allow IDP updates
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::where('email', $input['email'])->first();

            if ($user) {
                // Update existing user created by IDP intercept
                $user->update([
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
                    'password' => Hash::make($input['password']),
                    'privacy_consent' => true,
                    'privacy_consent_at' => now(),
                ]);
            } else {
                $user = User::create([
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
                    'email' => $input['email'],
                    'password' => Hash::make($input['password']),
                    'role_id' => 1, // using roles
                    'privacy_consent' => true, // User accepted terms during registration
                    'privacy_consent_at' => now(),
                ]);
            }

            // Update or Create applicant profile with high school data
            ApplicantProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'school' => $input['school'] ?? null,
                    'school_address' => $input['schoolAdd'] ?? null,
                    'school_year' => $input['schoolyear'] ?? null,
                    'date_graduated' => $input['dateGrad'] ?? null,
                    'strand' => $input['strand'] ?? null,
                    'track' => $input['track'] ?? null,
                ]
            );

            //log in user automatically
            Auth::login($user);
            
            return $user;
        });
    }

    /**
     * Create a personal team for the user.
     */
    protected function createTeam(User $user): void
    {
        $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0] . "'s Team",
            'personal_team' => true,
        ]));
    }
}
