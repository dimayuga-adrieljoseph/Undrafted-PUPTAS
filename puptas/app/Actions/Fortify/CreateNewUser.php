<?php

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use App\Models\ApplicantProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;
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
            'address' => ['required', 'string', 'max:255'],
            'school' => ['required', 'string', 'max:255'],
            'schoolAdd' => ['required', 'string', 'max:255'],
            'schoolyear' => ['required', 'string', 'max:50'],
            'dateGrad' => ['required', 'date'],
            'strand' => ['required', 'string', 'max:50'],
            'track' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return DB::transaction(function () use ($input) {
            return tap(User::create([
                'firstname' => $input['firstname'],
                'middlename' => $input['middlename'] ?? null,
                'lastname' => $input['lastname'],
                'birthday' => $input['birthday'],
                'sex' => $input['sex'],
                'contactnumber' => $input['contactnumber'],
                'address' => $input['address'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'role_id' => 1, // using roles
            ]), function (User $user) use ($input) {
                // Create applicant profile with high school data
                $user->applicantProfile()->create([
                    'school' => $input['school'] ?? null,
                    'school_address' => $input['schoolAdd'] ?? null,
                    'school_year' => $input['schoolyear'] ?? null,
                    'date_graduated' => $input['dateGrad'] ?? null,
                    'strand' => $input['strand'] ?? null,
                    'track' => $input['track'] ?? null,
                ]);

                //log in user automatically
                Auth::login($user);
            });
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
