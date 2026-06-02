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
        \Log::info('=== REGISTRATION ATTEMPT STARTED ===', [
            'input_keys' => array_keys($input),
            'has_session' => session()->has('pending_registration'),
        ]);
        
        $pendingReg = session('pending_registration');
        // Enforce IDP-first registration flow
        if (!$pendingReg) {
            \Log::error('Registration failed: No pending_registration session');
            abort(403, 'You must sign in via the IDP before completing registration.');
        }
        
        \Log::info('Pending registration found', [
            'email' => $pendingReg['email'] ?? 'MISSING',
            'has_access_token' => !empty($pendingReg['access_token']),
        ]);

        $rules = [
            'email' => ['nullable', 'string', 'email', 'max:255'],

            'lastname' => ['required', 'string', 'max:255'],
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', 'string'],
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

            // Block registration for Unqualified and Waitlisted Below Cutoff
            if (in_array($testPasser->passer_status_id, [3, 4])) {
                $statusName = $testPasser->passer_status_id === 3 ? 'Unqualified' : 'Waitlisted Below Cutoff';
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reference_number' => "Registration is currently closed for {$statusName} applicants. Please wait for further announcements regarding open slots.",
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
                'sex' => !empty($input['sex']) ? $input['sex'] : null,
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
                'sex' => !empty($input['sex']) ? $input['sex'] : null,
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
                    'user_id'          => $user->id,
                    'status'           => 'registered',
                    'surname'          => $input['lastname'],
                    'first_name'       => $input['firstname'],
                    'middle_name'      => $input['middlename'] ?? $testPasser->middle_name,
                    'strand'           => $input['strand'] ?? $testPasser->strand,
                    'shs_school'       => $input['school'] ?? $testPasser->shs_school,
                    'graduate_of'      => $input['schoolyear'] ?? $testPasser->graduate_of,
                    'graduation_date'  => $input['dateGrad'] ?? $testPasser->graduation_date,
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
                try {
                    // Store IDP tokens server-side only in Redis.
                    $expiresAt = $pendingReg['expires_at'] ?? now()->addHour();
                    $ttl = 60 * 60 * 24 * 30; // 30 days

                    \Illuminate\Support\Facades\Cache::store('redis')->put(
                        "idp_tokens:user_{$user->id}",
                        [
                            'access_token'  => $pendingReg['access_token'],
                            'refresh_token' => $pendingReg['refresh_token'] ?? null,
                            'expires_at'    => $expiresAt->timestamp,
                        ],
                        $ttl
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to store IDP tokens in Redis during registration', [
                        'error' => $e->getMessage()
                    ]);
                    // Continue registration even if Redis is unavailable locally
                }
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
                \Log::error('Registration validation failed', [
                    'errors' => $e->errors(),
                    'message' => $e->getMessage(),
                ]);
                throw $e;
            } catch (\Exception $e) {
                \Log::error('Registration failed in CreateNewUser', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'input' => array_diff_key($input, array_flip(['password'])),
                    'pending_reg_email' => $pendingReg['email'] ?? null,
                ]);
                
                // Only show detailed error for test accounts
                $testEmails = ['dummyjm15@gmail.com', 'test@example.com'];
                if (in_array($pendingReg['email'] ?? '', $testEmails)) {
                    throw new \Exception('DEBUG: ' . $e->getMessage() . ' at line ' . $e->getLine() . ' in ' . basename($e->getFile()), 0, $e);
                }
                
                throw $e;
            }
        });
    }

    // Teams management is not needed for IDP string users
}
