<?php

use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\Program;
use App\Models\ApplicantProfile;
use App\Auth\IdpUser;

function makeApplicant(array $overrides = [])
{
    static $seq = 100;
    $seq++;
    $uid = 'app-' . $seq;
    ApplicantProfile::create([
        'user_id' => $uid,
        'firstname' => 'Test',
        'lastname' => 'Applicant' . $seq,
        'email' => 'app' . $seq . '@example.com',
    ]);
    return new IdpUser(['id' => $uid, 'role_id' => 1]);
}

function makeInterviewer()
{
    return new IdpUser(['id' => 'int-1', 'role_id' => 4]);
}

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

function makeEnrolledApplication($applicant, Program $program): Application
{
    $application = Application::create([
        'user_id'           => $applicant->id,
        'program_id'        => $program->id,
        'status'            => 'accepted',
        'enrollment_status' => 'officially_enrolled',
    ]);
    ApplicationProcess::create(['application_id' => $application->id, 'stage' => 'medical', 'status' => 'completed']);
    ApplicationProcess::create(['application_id' => $application->id, 'stage' => 'records', 'status' => 'completed']);
    return $application;
}

test('interviewer can change course for an officially enrolled applicant', function () {
    $this->withoutExceptionHandling();
    $applicant   = makeApplicant();
    $interviewer = makeInterviewer();
    $programA    = makeProgram('P-A');
    $programB    = makeProgram('P-B');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($interviewer)
        ->postJson("/record-dashboard/change-course/{$applicant->id}", [
            'program_id' => $programB->id,
        ])
        ->assertOk()
        ->assertJson(['message' => 'Course updated successfully.']);

    $this->assertDatabaseHas('applications', [
        'user_id'           => $applicant->id,
        'program_id'        => $programB->id,
        'enrollment_status' => 'officially_enrolled',
    ]);
});

test('a course_changed ApplicationProcess row is created on success', function () {
    $this->withoutExceptionHandling();
    $applicant   = makeApplicant();
    $interviewer = makeInterviewer();
    $programA    = makeProgram('CCA');
    $programB    = makeProgram('CCB');

    $application = makeEnrolledApplication($applicant, $programA);

    $this->actingAs($interviewer)
        ->postJson("/record-dashboard/change-course/{$applicant->id}", [
            'program_id' => $programB->id,
        ])
        ->assertOk();

    $this->assertDatabaseHas('application_processes', [
        'application_id' => $application->id,
        'action'         => 'course_changed',
        'status'         => 'completed',
    ]);
});

test('admin (role 2) can change course', function () {
    $admin = new IdpUser(['id' => 'adm-1', 'role_id' => 2]);
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
    $superadmin = new IdpUser(['id' => 'sadm-1', 'role_id' => 7]);
    $applicant  = makeApplicant();
    $programA   = makeProgram('SA');
    $programB   = makeProgram('SB');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($superadmin)
        ->postJson("/record-dashboard/change-course/{$applicant->id}", [
            'program_id' => $programB->id,
        ])
        ->assertOk();
});

test('registrar (role 6) cannot change course', function () {
    $registrar = new IdpUser(['id' => 'reg-1', 'role_id' => 6]);
    $applicant = makeApplicant();
    $programA  = makeProgram('TA');
    $programB  = makeProgram('TB');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($registrar)
        ->postJson("/record-dashboard/change-course/{$applicant->id}", [
            'program_id' => $programB->id,
        ])
        ->assertForbidden();
});

test('evaluator (role 3) cannot change course', function () {
    $evaluator = new IdpUser(['id' => 'eval-1', 'role_id' => 3]);
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
    $programB  = makeProgram('GB');

    $this->post("/record-dashboard/change-course/{$applicant->id}", [
        'program_id' => $programB->id,
    ])
        ->assertRedirect('/login');
});

test('cannot change course when applicant is not officially enrolled', function () {
    $this->withoutExceptionHandling();
    $applicant   = makeApplicant();
    $interviewer = makeInterviewer();
    $programA    = makeProgram('NE-A');
    $programB    = makeProgram('NE-B');

    $application = Application::create([
        'user_id'           => $applicant->id,
        'program_id'        => $programA->id,
        'status'            => 'submitted',
        'enrollment_status' => 'pending',
    ]);

    $this->actingAs($interviewer)
        ->postJson("/record-dashboard/change-course/{$applicant->id}", [
            'program_id' => $programB->id,
        ])
        ->assertStatus(409)
        ->assertJson(['message' => 'Course can only be changed for officially enrolled applicants.']);
});

test('changing to the same program returns 422', function () {
    $this->withoutExceptionHandling();
    $applicant   = makeApplicant();
    $interviewer = makeInterviewer();
    $programA    = makeProgram('SAME');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($interviewer)
        ->postJson("/record-dashboard/change-course/{$applicant->id}", [
            'program_id' => $programA->id,
        ])
        ->assertStatus(422)
        ->assertJson(['message' => 'The selected program is the same as the current program.']);
});

test('cannot change course to a non-existent program', function () {
    $applicant   = makeApplicant();
    $interviewer = makeInterviewer();
    $programA    = makeProgram('EXA');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($interviewer)
        ->postJson("/record-dashboard/change-course/{$applicant->id}", [
            'program_id' => 999999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['program_id']);
});

test('request without program_id fails validation', function () {
    $applicant   = makeApplicant();
    $interviewer = makeInterviewer();
    $programA  = makeProgram('VA');

    makeEnrolledApplication($applicant, $programA);

    $this->actingAs($interviewer)
        ->postJson("/record-dashboard/change-course/{$applicant->id}", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['program_id']);
});
