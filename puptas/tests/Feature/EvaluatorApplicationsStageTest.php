<?php

use App\Enums\RoleId;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin hitting /document-evaluator-dashboard receives document_evaluator stage prop', function () {
    $admin = User::factory()->create(['role_id' => RoleId::Admin->value]);

    $response = $this->actingAs($admin)
        ->withoutVite()
        ->get('/document-evaluator-dashboard');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard/Evaluator')
        ->where('stage', 'document_evaluator')
    );
});

test('admin hitting /document-evaluator-applications receives document_evaluator stage prop', function () {
    $admin = User::factory()->create(['role_id' => RoleId::Admin->value]);

    $response = $this->actingAs($admin)
        ->withoutVite()
        ->get('/document-evaluator-applications');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Applications/Evaluator')
        ->where('stage', 'document_evaluator')
    );
});

test('admin hitting /evaluator-applications with stage query param receives requested stage prop', function (string $stage) {
    $admin = User::factory()->create(['role_id' => RoleId::Admin->value]);

    $response = $this->actingAs($admin)
        ->withoutVite()
        ->get("/evaluator-applications?stage={$stage}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Applications/Evaluator')
        ->where('stage', $stage)
    );
})->with([
    'document_evaluator' => 'document_evaluator',
    'grade_evaluator'    => 'grade_evaluator',
]);

test('document evaluator hitting /document-evaluator-applications receives document_evaluator stage prop', function () {
    $evaluator = User::factory()->create(['role_id' => RoleId::DocumentEvaluator->value]);

    $response = $this->actingAs($evaluator)
        ->withoutVite()
        ->get('/document-evaluator-applications');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Applications/Evaluator')
        ->where('stage', 'document_evaluator')
    );
});

test('superadmin hitting /document-evaluator-applications receives document_evaluator stage prop', function () {
    $superAdmin = User::factory()->create(['role_id' => RoleId::SuperAdmin->value]);

    $response = $this->actingAs($superAdmin)
        ->withoutVite()
        ->get('/document-evaluator-applications');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Applications/Evaluator')
        ->where('stage', 'document_evaluator')
    );
});
