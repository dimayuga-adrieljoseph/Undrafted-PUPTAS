<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationProcess;
use App\Models\ApplicantProfile;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('submitting twice sequentially returns 200 then 409 and creates exactly one document_evaluator process', function () {
    // Ensure cutoff is not blocking
    if (!DB::table('cutoff_settings')->where('id', 1)->exists()) {
        DB::table('cutoff_settings')->insert([
            'id' => 1,
            'cutoff_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } else {
        DB::table('cutoff_settings')->where('id', 1)->update(['cutoff_at' => null]);
    }

    // Ensure programs exist
    $program1 = Program::firstOrCreate(
        ['id' => 1],
        ['code' => 'BSCS', 'name' => 'Computer Science', 'capacity' => 50, 'slots' => 50]
    );
    $program2 = Program::firstOrCreate(
        ['id' => 2],
        ['code' => 'BSIT', 'name' => 'Information Technology', 'capacity' => 50, 'slots' => 50]
    );
    $program3 = Program::firstOrCreate(
        ['id' => 3],
        ['code' => 'BSIS', 'name' => 'Information Systems', 'capacity' => 50, 'slots' => 50]
    );

    // Create applicant user
    /** @var User $applicant */
    $applicant = User::forceCreate([
        'firstname'  => 'Test',
        'lastname'   => 'Applicant',
        'email'      => 'applicant_' . uniqid() . '@test.com',
        'password'   => bcrypt('password'),
        'role_id'    => 1,
        'sex'        => 'Female',
    ]);

    // Create applicant profile
    ApplicantProfile::create([
        'user_id' => $applicant->id,
        'firstname' => 'Test',
        'lastname' => 'Applicant',
        'email' => $applicant->email,
        'first_choice_program' => $program1->id,
        'second_choice_program' => $program2->id,
        'third_choice_program' => $program3->id,
    ]);

    $payload = [
        'program_id'       => $program1->id,
        'second_choice_id' => $program2->id,
        'third_choice_id'  => $program3->id,
    ];

    // First submission: should succeed with 200
    $firstResponse = $this->actingAs($applicant)
        ->postJson('/user/application/submit', $payload);

    $firstResponse->assertStatus(200);
    $firstResponse->assertJsonPath('status', 'submitted');

    // Second submission: sequential repeat should fail with 409 Conflict
    $secondResponse = $this->actingAs($applicant)
        ->postJson('/user/application/submit', $payload);

    $secondResponse->assertStatus(409);
    $secondResponse->assertJsonPath('message', 'Application has already been submitted.');

    // Assert database state: exactly ONE document_evaluator process row
    $application = Application::where('user_id', $applicant->id)->first();
    expect($application)->not->toBeNull();

    $docEvaluatorProcesses = ApplicationProcess::where('application_id', $application->id)
        ->where('stage', 'document_evaluator')
        ->get();

    expect($docEvaluatorProcesses)->toHaveCount(1);
    expect($docEvaluatorProcesses->first()->status)->toBe('in_progress');
});
