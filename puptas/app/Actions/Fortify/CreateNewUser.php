<?php

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use App\Models\ApplicantProfile;
use App\Models\TestPasser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Illuminate\Support\Facades\Auth;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    // No password needed — users authenticate via IDP
    protected function passwordRules(): array
    {
        return [];
    }

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input)
    {
        $pendingReg = session('pending_registration');
        // Enforce IDP-first registration flow
        if (!$pendingReg) {
            abort(403, 'You must sign in via the IDP before completing registration.');
        }

        $rules = [
            'email' => ['nullable', 'string', 'email', 'max:255'],

            'lastname' => ['required', 'string', 'max:255'],
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', 'string'],
            'contactnumber' => ['nullable', 'string', 'max:15'],
            'reference_number' => ['required', 'string', 'max:100'],
            'schoolyear' => ['required', 'string', 'exists:graduate_types,label'],
            'school' => ['required', 'string', 'max:255'],
        ];

        Validator::make($input, $rules)->validate();

        return DB::transaction(function () use ($input, $pendingReg) {
            try {
            $email = strtolower(trim($pendingReg['email'] ?? ''));

            if (!$email) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => 'No IDP email found in session. Please sign in via IDP again.',
                ]);
            }

            if (User::where('email', $email)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => 'An account with this email already exists.',
                ]);
            }

            // Validate reference number before writing anything to the database.
            // Only test passers are allowed to register — reject early if not found.
            $inputRefNumber = trim($input['reference_number']);
            $testPasser = TestPasser::where('reference_number', $inputRefNumber)->first();

            if (!$testPasser) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reference_number' => 'The reference number you entered is not recognized. Only admitted test passers are allowed to create an account. Please verify your reference number and try again.',
                ]);
            }

            // All checks passed — create the local User record
            $user = User::create([
                'idp_user_id' => $pendingReg['user_id'] ?? (string) \Illuminate\Support\Str::uuid(),
                'email' => $email,
                'role_id' => 1,
                'firstname' => $input['firstname'],
                'lastname' => $input['lastname'],
                'middlename' => $input['middlename'] ?? null,
                'sex' => $input['sex'] ?? null,
                'contactnumber' => !empty($input['contactnumber']) ? $input['contactnumber'] : 'N/A',
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(12)),
                'privacy_consent' => true,
                'privacy_consent_at' => now(),
            ]);

            $profile = ApplicantProfile::create([
                'user_id' => $user->id,
                'email' => $email,
                'firstname' => $input['firstname'],
                'middlename' => $input['middlename'] ?? null,
                'lastname' => $input['lastname'],
                'sex' => $input['sex'] ?? null,
                'contactnumber' => !empty($input['contactnumber']) ? $input['contactnumber'] : 'N/A',
                'date_graduated' => $input['dateGrad'] ?? null,
                'school' => $input['school'],
                'strand' => $input['strand'] ?? null,
                'track' => $input['track'] ?? null,
                'privacy_consent' => true,
                'privacy_consent_at' => now(),
            ]);

            // Link TestPasser record to the user, update status, and sync
            // the latest information the user provided during registration.
            if ($testPasser) {
                $testPasser->update([
                    'user_id'        => $user->id,
                    'status'         => 'registered',
                    'surname'        => $input['lastname'],
                    'first_name'     => $input['firstname'],
                    'middle_name'    => $input['middlename'] ?? $testPasser->middle_name,
                    'strand'         => $input['strand'] ?? $testPasser->strand,
                    'shs_school'     => $input['school'] ?? $testPasser->shs_school,
                ]);
            }

            // Attach graduate type via junction table
            if (!empty($input['schoolyear'])) {
                $graduateType = \App\Models\GraduateType::where('label', $input['schoolyear'])->first();
                if ($graduateType) {
                    $profile->graduateTypes()->sync([$graduateType->id]);
                }
            }

            if (!empty($pendingReg['access_token'])) {
                // Store IDP tokens server-side only — never expose them in browser cookies.
                // The IDP callback flow already follows this pattern (see IdpAuthController@callback).
                \App\Models\RefreshToken::create([
                    'user_id'       => $user->id,
                    'access_token'  => $pendingReg['access_token'],
                    'refresh_token' => $pendingReg['refresh_token'] ?? null,
                    'expires_at'    => $pendingReg['expires_at'] ?? now()->addHour(),
                ]);
            }

            // Clear the pending registration from session
            session()->forget('pending_registration');

            // Log the user in — IDP credentials were already validated and saved above
            Auth::login($user);

            \Log::info('User registration completed successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return $user;

            } catch (\Illuminate\Validation\ValidationException $e) {
                // Re-throw validation exceptions so they show field-specific errors
                throw $e;
            } catch (\Exception $e) {
                \Log::error('Registration failed in CreateNewUser', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                    'input' => array_diff_key($input, array_flip(['password'])),
                    'pending_reg_email' => $pendingReg['email'] ?? null,
                ]);
                
                // In development, show the actual error
                if (config('app.debug')) {
                    throw new \Exception('Registration failed: ' . $e->getMessage(), 0, $e);
                }
                
                throw $e;
            }
        });
    }

    // Teams management is not needed for IDP string users
}
