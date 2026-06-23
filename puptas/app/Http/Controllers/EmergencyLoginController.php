<?php

namespace App\Http\Controllers;

use App\Mail\EmergencyOtpMail;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class EmergencyLoginController extends Controller
{
    /**
     * Show the emergency login form to request an OTP.
     */
    public function showLoginForm()
    {
        if (!$this->isEmergencyLoginEnabled()) {
            return redirect('/');
        }
        return Inertia::render('Auth/EmergencyLogin');
    }

    /**
     * Process the email submission and send OTP.
     */
    public function sendOtp(Request $request)
    {
        if (!$this->isEmergencyLoginEnabled()) {
            return redirect('/');
        }

        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        // Prevent leaking if email exists or not for security, just act like it worked.
        // But for UX we might want to tell them. Since this is an emergency, we'll tell them.
        if (!$user) {
            return back()->withErrors(['email' => 'No account found with this email address.']);
        }

        // Check if user is an applicant with restricted status
        if ((int) $user->role_id === 1) {
            $testPasser = \App\Models\TestPasser::where('email', $request->email)->first();
            if ($testPasser && in_array($testPasser->passer_status_id, [3, 4])) {
                $message = $testPasser->passer_status_id === 3 
                    ? 'Login is not available for Unqualified applicants.' 
                    : 'Login is currently closed for Waitlisted applicants.';
                return back()->withErrors(['email' => $message]);
            }
        }

        $otp = sprintf("%06d", random_int(100000, 999999));
        
        // Store in cache for 5 minutes
        Cache::put('emergency_otp_' . $user->email, $otp, now()->addMinutes(5));

        // Send Email
        Mail::to($user->email)->send(new EmergencyOtpMail($otp));

        // Store email in session to verify in the next step
        session(['emergency_login_email' => $user->email]);

        return redirect()->route('emergency.verify-form')->with('success', 'A 6-digit OTP has been sent to your email.');
    }

    /**
     * Show the OTP verification form.
     */
    public function showVerifyForm()
    {
        if (!$this->isEmergencyLoginEnabled()) {
            return redirect('/');
        }

        if (!session('emergency_login_email')) {
            return redirect()->route('emergency.login');
        }

        return Inertia::render('Auth/EmergencyVerify', [
            'email' => session('emergency_login_email')
        ]);
    }

    /**
     * Verify the OTP and login the user.
     */
    public function verifyOtp(Request $request)
    {
        if (!$this->isEmergencyLoginEnabled()) {
            return redirect('/');
        }

        $request->validate([
            'otp' => 'required|string|size:6'
        ]);

        $email = session('emergency_login_email');
        if (!$email) {
            return redirect()->route('emergency.login')->withErrors(['email' => 'Session expired. Please try again.']);
        }

        $cachedOtp = Cache::get('emergency_otp_' . $email);

        $attemptsKey = 'emergency_otp_attempts_' . $email;
        $attempts = Cache::get($attemptsKey, 0);

        if ($attempts >= 3) {
            Cache::forget('emergency_otp_' . $email);
            Cache::forget($attemptsKey);
            return back()->withErrors(['otp' => 'Too many invalid attempts. Please request a new OTP.']);
        }

        if (!$cachedOtp || $cachedOtp !== $request->otp) {
            Cache::add($attemptsKey, 0, now()->addMinutes(5));
            Cache::increment($attemptsKey);
            return back()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }

        // OTP is valid - clear attempts
        Cache::forget($attemptsKey);

        // OTP is valid
        $user = User::where('email', $email)->first();
        if (!$user) {
            return redirect()->route('emergency.login')->withErrors(['email' => 'Account not found.']);
        }

        // Clear cache and session
        Cache::forget('emergency_otp_' . $email);
        session()->forget('emergency_login_email');

        // Set emergency login session flag
        session(['emergency_logged_in' => true]);

        // Clear any stale IDP tokens from Redis so middleware doesn't attempt to refresh them
        try {
            Cache::store('redis')->forget("idp_tokens:user_{$user->id}");
        } catch (\Exception $e) {
            \Log::warning('Failed to clear stale IDP tokens during emergency login', ['error' => $e->getMessage()]);
        }

        // Login user
        Auth::login($user);
        \Log::info('User logged in via Emergency OTP', ['user_id' => $user->id, 'email' => $user->email]);

        $roleId = (int) $user->role_id;
        $response = redirect('/dashboard');
        
        switch ($roleId) {
            case 1:
                $response = redirect('/applicant-dashboard');
                break;
            case 2:
            case 7:
                $response = redirect('/dashboard');
                break;
            case 3:
            case 8:
                $response = redirect('/evaluator-dashboard');
                break;
            case 4:
                $response = redirect('/interviewer-dashboard');
                break;
            case 6:
                $response = redirect('/record-dashboard');
                break;
        }

        return $response;
    }

    /**
     * Check if the setting is enabled.
     */
    private function isEmergencyLoginEnabled(): bool
    {
        $setting = SystemSetting::where('key', 'idp_down_emergency_login_enabled')->first();
        return $setting && $setting->value === '1';
    }
}
