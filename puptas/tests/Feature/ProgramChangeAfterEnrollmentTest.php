<?php

/**
 * Feature tests for Program Change After Enrollment.
 *
 * Covers:
 *  1.  Interviewer can change course for an officially enrolled applicant.
 *  2.  Correct ApplicationProcess audit row is persisted.
 *  3.  Non-authorized roles (e.g. Registrar role 6, Evaluator role 3) receive 403.
 *  4.  Guest receives a redirect to /login.
 *  5.  Cannot change course when applicant is NOT officially enrolled.
 *  6.  Cannot change course to the same program (no-op, 422).
 *  7.  Cannot change course to a non-existent program (422 / validation error).
 *  8.  Changing to the same program_id returns 422.
 */

use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Create a minimal applicant (role_id 1) with the required fields.
 */
function makeApplicant(array $overrides = []): User
{
    static $seq = 100;
    $seq++;

    return User::create(array_merge([
        'firstname'           => 'Test',
        'lastname'            => 'Applicant' . $seq,
        'email'               => 'applicant' . $seq . '@example.com',
        'contactnumber'       => '091700000' . ($seq % 100),
        'password'            => Hash::make('password'),
        'role_id'             => 1,
        'privacy_consent'     => true,
        'privacy_consent_at'  => now(),
        'email_verified_at'   => now(),
    ], $overrides));
}

/**
 * Create an Interviewer (role_id 4).
 */
function makeInterviewer(): User
{
    static $iseq = 400;
    $iseq++;

    return User::create([
        'firstname'           => 'Interviewer',
        'lastname'            => 'Staff' . $iseq,
        'email'               => 'interviewer' . $iseq . '@example.com',
        'contactnumber'       => '091700' . $iseq,
        'password'            => Hash::make('password'),
        'role_id'             => 4,
        'privacy_consent'     => true,
        'privacy_consent_at'  => now(),
        'email_verified_at'   => now(),
    ]);
}

/**
 * Create a Program row directly (bypasses capacity validation, etc.).
 */
function makeProgram(string $code = null): Program
{
    static $pseq = 300;
    $pseq++;
    $code ??= 'PROG' . $pseq;

    return Program::create([
        'code'     => $code,
        'name'     => 'Program ' . $pseq,
        'capacity' => 50,
        'slots'    => 50,
    ]);
}

/**
 * Attach an officially-enrolled Application to $applicant on $program.
 * Also creates the prerequisite medical/records process rows.
 */
function makeEnrolledApplication(User $applicant, Program $program): Application
{
    $app = Application::create([
        'user_id'           => $applicant->id,
        'program_id'        => $program->id,
        'status'            => 'accepted',
        'enrollment_status' => 'officially_enrolled',
        'submitted_at'      => now(),
    ]);

    // Medical stage — completed (prerequisite for records stage)
    ApplicationProcess::create([
        'application_id' => $app->id,
        'stage'          => 'medical',
        'status'         => 'completed',
        'action'         => 'passed',
        'performed_by'   => null,
    ]);

    // Records stage — completed (the enrollment tag)
    ApplicationProcess::create([
        'application_id'  => $app->id,
        'stage'           => 'records',
        'status'          => 'completed',
        'action'          => 'transferred',
        'decision_reason' => 'officially_enrolled',
        'performed_by'    => null,
    ]);

    return $app;
}

// ─── Test: Happy path ─────────────────────────────────────────────────────────

test('interviewer can change course for an officially enrolled applicant', function () {
    $interviewer = makeInterviewer();
    $applicant   = makeApplicant();
    $programA    = makeProgram('PA');
    $programB    = makeProgram('PB');

    $app = makeEnrolledApplication($applicant, $programA);

    $response = $this
        ->actingAs($interviewer)
        ->postJson("/record-dashboard/change-course/{$applicant->id}", [
            'program_id' => $programB->id,
        ]);

    $response->assertOk()
             ->assertJsonFragment(['message' => 'Course updated successfully.']);

    // Application.program_id must be updated
    $this->assertDatabaseHas('applications', [
        'id'                => $app->id,
        'program_id'        => $programB->id,
        'enrollment_status' => 'officially_enrolled',   // status preserved
        'status'            => 'accepted',               // status preserved
    ]);
});

test('a course_changed ApplicationProcess row is created on success', function () {
    $interviewer = makeInterviewer();
    $applicant   = makeApplicant();
    $programA    = makeProgram('QA');
    $programB    = makeProgram('QB');

    $app = makeEnrolledApplication($applicant, $programA);

    $this->actingAs($interviewer)
         ->postJson("/record-dashboard/change-course/{$applicant->id}", [
             'program_id' => $programB->id,
         ])
         ->assertOk();

    $this->assertDatabaseHas('application_processes', [
        'application_id'  => $app->id,
        'stage'           => 'records',
        'status'          => 'completed',
        'action'          => 'course_changed',
        'decision_reason' => 'program_change',
        'performed_by'    => $interviewer->id,
    ]);
});

// ─── Test: Authorization ──────────────────────────────────────────────────────

test('admin (role 2) can change course', function () {
    $admin     = User::create([
        'firstname'         => 'Admin',
        'lastname'          => 'User',
        'email'             => 'admin_cc@example.com',
        'contactnumber'     => '0917001234',
        'password'          => Hash::make('password'),
        'role_id'           => 2,
        'privacy_consent'   => true,
        'privacy_consent_at'=> now(),
        'email_verified_at' => now(),
    ]);
    $applicant = makeApplicant();
    $programA  = makeProgram('RA');
    $programB  = makeProgram('RB');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($admin)
         ->postJson("/record-dashboard/change-course/{$applicant->id}", [
             'program_id' => $programB->id,
         ])
         ->assertOk();
});

test('superadmin (role 7) can change course', function () {
    $superadmin = User::create([
        'firstname'         => 'Superadmin',
        'lastname'          => 'User',
        'email'             => 'superadmin_cc@example.com',
        'contactnumber'     => '0917001235',
        'password'          => Hash::make('password'),
        'role_id'           => 7,
        'privacy_consent'   => true,
        'privacy_consent_at'=> now(),
        'email_verified_at' => now(),
    ]);
    $applicant = makeApplicant();
    $programA  = makeProgram('SA1');
    $programB  = makeProgram('SA2');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($superadmin)
         ->postJson("/record-dashboard/change-course/{$applicant->id}", [
             'program_id' => $programB->id,
         ])
         ->assertOk();
});

test('registrar (role 6) cannot change course', function () {
    $registrar = User::create([
        'firstname'         => 'Registrar',
        'lastname'          => 'NoAccess',
        'email'             => 'registrar_no_cc@example.com',
        'contactnumber'     => '0917001236',
        'password'          => Hash::make('password'),
        'role_id'           => 6,
        'privacy_consent'   => true,
        'privacy_consent_at'=> now(),
        'email_verified_at' => now(),
    ]);
    $applicant = makeApplicant();
    $programA  = makeProgram('REG_A');
    $programB  = makeProgram('REG_B');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($registrar)
         ->postJson("/record-dashboard/change-course/{$applicant->id}", [
             'program_id' => $programB->id,
         ])
         ->assertForbidden();
});

test('evaluator (role 3) cannot change course', function () {
    $evaluator = User::create([
        'firstname'         => 'Evaluator',
        'lastname'          => 'User',
        'email'             => 'evaluator_cc@example.com',
        'contactnumber'     => '0917001235',
        'password'          => Hash::make('password'),
        'role_id'           => 3,
        'privacy_consent'   => true,
        'privacy_consent_at'=> now(),
        'email_verified_at' => now(),
    ]);
    $applicant = makeApplicant();
    $programA  = makeProgram('EA');
    $programB  = makeProgram('EB');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($evaluator)
         ->postJson("/record-dashboard/change-course/{$applicant->id}", [
             'program_id' => $programB->id,
         ])
         ->assertForbidden();
});

test('guest is redirected when attempting a course change', function () {
    $applicant = makeApplicant();
    $programA  = makeProgram('GA');
    $programB  = makeProgram('GB');

    makeEnrolledApplication($applicant, $programA);

    $this->postJson("/record-dashboard/change-course/{$applicant->id}", [
             'program_id' => $programB->id,
         ])
         ->assertUnauthorized();
});

// ─── Test: Business rule — enrollment status guard ────────────────────────────

test('cannot change course when applicant is not officially enrolled', function () {
    $interviewer = makeInterviewer();
    $applicant = makeApplicant();
    $programA  = makeProgram('SA');
    $programB  = makeProgram('SB');

    // Application in 'temporary' / 'waitlist' state — not officially enrolled
    Application::create([
        'user_id'           => $applicant->id,
        'program_id'        => $programA->id,
        'status'            => 'waitlist',
        'enrollment_status' => 'temporary',
        'submitted_at'      => now(),
    ]);

    $this->actingAs($interviewer)
         ->postJson("/record-dashboard/change-course/{$applicant->id}", [
             'program_id' => $programB->id,
         ])
         ->assertStatus(409);
});

// ─── Test: Business rule — same-program guard ─────────────────────────────────

test('changing to the same program returns 422', function () {
    $interviewer = makeInterviewer();
    $applicant = makeApplicant();
    $programA  = makeProgram('TA');

    $app = makeEnrolledApplication($applicant, $programA);

    $this->actingAs($interviewer)
         ->postJson("/record-dashboard/change-course/{$applicant->id}", [
             'program_id' => $programA->id,   // same program
         ])
         ->assertStatus(422);

    // Application must be unchanged
    $this->assertDatabaseHas('applications', [
        'id'         => $app->id,
        'program_id' => $programA->id,
    ]);
});

// ─── Test: Validation — non-existent program ──────────────────────────────────

test('cannot change course to a non-existent program', function () {
    $interviewer = makeInterviewer();
    $applicant = makeApplicant();
    $programA  = makeProgram('UA');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($interviewer)
         ->postJson("/record-dashboard/change-course/{$applicant->id}", [
             'program_id' => 99999,   // non-existent
         ])
         ->assertUnprocessable()    // 422
         ->assertJsonValidationErrors(['program_id']);
});

test('request without program_id fails validation', function () {
    $interviewer = makeInterviewer();
    $applicant = makeApplicant();
    $programA  = makeProgram('VA');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($interviewer)
         ->postJson("/record-dashboard/change-course/{$applicant->id}", [])
         ->assertUnprocessable()
         ->assertJsonValidationErrors(['program_id']);
});
