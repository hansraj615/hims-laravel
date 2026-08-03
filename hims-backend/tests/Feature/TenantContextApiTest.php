<?php

use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Department;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Hospitals\Models\HospitalGroup;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns the authenticated user current tenant context', function () {
    $this->seed(HimsDemoSeeder::class);

    $user = User::where('email', 'admin@example.com')->firstOrFail();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/context');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Current hospital context loaded')
        ->assertJsonPath('data.hospital.code', 'DEMOHIMS')
        ->assertJsonPath('data.branch.code', 'MAIN')
        ->assertJsonPath('data.user.email', 'admin@example.com');

    expect($response->json('data.user.permissions'))->toContain('context.view');
    expect($response->json('data.available_assignments'))->toBeArray()->not->toBeEmpty();
    expect($response->json('data.available_assignments.0.hospital.code'))->toBe('DEMOHIMS');
});

it('switches hospital branch context using assignment headers', function () {
    $this->seed(HimsDemoSeeder::class);

    $user = User::where('email', 'admin@example.com')->firstOrFail();
    $hospital = Hospital::where('code', 'DEMOHIMS')->firstOrFail();
    $branch = Branch::create([
        'hospital_id' => $hospital->id,
        'name' => 'East Branch',
        'code' => 'EAST',
        'facility_type' => 'clinic',
        'timezone' => 'Asia/Kolkata',
        'status' => 'active',
    ]);

    $user->hospitalAssignments()->create([
        'hospital_id' => $hospital->id,
        'branch_id' => $branch->id,
        'assignment_type' => 'staff',
        'is_default' => false,
        'status' => 'active',
        'approved_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this
        ->withHeaders([
            'X-Hospital-Id' => (string) $hospital->id,
            'X-Branch-Id' => (string) $branch->id,
        ])
        ->getJson('/api/v1/context')
        ->assertOk()
        ->assertJsonPath('data.branch.code', 'EAST');
});

it('denies a requested hospital context when the user has no approved assignment', function () {
    $this->seed(HimsDemoSeeder::class);

    $user = User::where('email', 'admin@example.com')->firstOrFail();

    $externalGroup = HospitalGroup::create(['name' => 'External Group']);
    $externalHospital = Hospital::create([
        'hospital_group_id' => $externalGroup->id,
        'name' => 'External Hospital',
        'code' => 'EXT',
        'status' => 'active',
    ]);

    Sanctum::actingAs($user);

    $response = $this
        ->withHeader('X-Hospital-Id', (string) $externalHospital->id)
        ->getJson('/api/v1/context');

    $response
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('scopes administration department results to the resolved tenant context', function () {
    $this->seed(HimsDemoSeeder::class);

    $user = User::where('email', 'admin@example.com')->firstOrFail();

    $externalGroup = HospitalGroup::create(['name' => 'External Group']);
    $externalHospital = Hospital::create([
        'hospital_group_id' => $externalGroup->id,
        'name' => 'External Hospital',
        'code' => 'EXT',
        'status' => 'active',
    ]);
    $externalBranch = Branch::create([
        'hospital_id' => $externalHospital->id,
        'name' => 'External Branch',
        'code' => 'EXTMAIN',
        'status' => 'active',
    ]);
    Department::create([
        'hospital_id' => $externalHospital->id,
        'branch_id' => $externalBranch->id,
        'name' => 'External Department',
        'code' => 'EXTDEPT',
        'department_type' => 'clinical',
        'status' => 'active',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/admin/departments');

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    $departmentNames = collect($response->json('data'))->pluck('name');

    expect($departmentNames)
        ->toContain('General Medicine')
        ->not->toContain('External Department');
});

it('requires authentication for tenant context', function () {
    $response = $this->getJson('/api/v1/context');

    $response
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
});
