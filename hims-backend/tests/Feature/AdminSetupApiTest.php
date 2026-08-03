<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Department;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Hospitals\Models\HospitalGroup;
use App\Domain\Hospitals\Models\UserHospitalBranchAssignment;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
    Sanctum::actingAs(User::where('email', 'admin@example.com')->firstOrFail());
});

it('creates and updates branches within the resolved hospital', function () {
    $create = $this->postJson('/api/v1/admin/branches', [
        'name' => 'South Branch',
        'code' => 'SOUTH',
        'facility_type' => 'clinic',
        'address' => 'South Road',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'pincode' => '400002',
        'phone' => '+912200000000',
        'timezone' => 'Asia/Kolkata',
        'status' => 'active',
    ]);

    $create
        ->assertCreated()
        ->assertJsonPath('message', 'Branch created')
        ->assertJsonPath('data.code', 'SOUTH');

    $branchId = $create->json('data.id');

    $update = $this->putJson("/api/v1/admin/branches/{$branchId}", [
        'name' => 'South OPD Branch',
        'code' => 'SOUTH',
        'facility_type' => 'clinic',
        'address' => 'South Road',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'pincode' => '400002',
        'phone' => '+912200000000',
        'timezone' => 'Asia/Kolkata',
        'status' => 'active',
    ]);

    $update
        ->assertOk()
        ->assertJsonPath('data.name', 'South OPD Branch');
});

it('lists inactive branches for hospital administration', function () {
    $hospital = Hospital::where('code', 'DEMOHIMS')->firstOrFail();
    Branch::create([
        'hospital_id' => $hospital->id,
        'name' => 'Closed Branch',
        'code' => 'CLOSED',
        'facility_type' => 'clinic',
        'timezone' => 'Asia/Kolkata',
        'status' => 'inactive',
    ]);

    $response = $this->getJson('/api/v1/admin/branches');

    $response
        ->assertOk()
        ->assertJsonFragment(['code' => 'CLOSED'])
        ->assertJsonFragment(['status' => 'inactive']);
});

it('creates departments scoped to the resolved hospital', function () {
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    $response = $this->postJson('/api/v1/admin/departments', [
        'branch_id' => $branch->id,
        'name' => 'Radiology',
        'code' => 'RAD',
        'department_type' => 'diagnostic',
        'status' => 'active',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.name', 'Radiology');

    expect(Department::where('code', 'RAD')->firstOrFail()->hospital_id)
        ->toBe(Hospital::where('code', 'DEMOHIMS')->firstOrFail()->id);
});

it('lists departments across all branches for hospital administration', function () {
    $hospital = Hospital::where('code', 'DEMOHIMS')->firstOrFail();
    $branch = Branch::create([
        'hospital_id' => $hospital->id,
        'name' => 'North Branch',
        'code' => 'NORTHADMIN',
        'facility_type' => 'clinic',
        'timezone' => 'Asia/Kolkata',
        'status' => 'active',
    ]);
    Department::create([
        'hospital_id' => $hospital->id,
        'branch_id' => $branch->id,
        'name' => 'Medicine',
        'code' => 'MED123',
        'department_type' => 'clinical',
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/v1/admin/departments');

    $response
        ->assertOk()
        ->assertJsonFragment(['code' => 'MED123'])
        ->assertJsonFragment(['name' => 'Medicine']);
});

it('lists inactive departments for hospital administration', function () {
    $hospital = Hospital::where('code', 'DEMOHIMS')->firstOrFail();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();
    Department::create([
        'hospital_id' => $hospital->id,
        'branch_id' => $branch->id,
        'name' => 'Archived Ward',
        'code' => 'ARCHWARD',
        'department_type' => 'clinical',
        'status' => 'inactive',
    ]);

    $response = $this->getJson('/api/v1/admin/departments');

    $response
        ->assertOk()
        ->assertJsonFragment(['code' => 'ARCHWARD'])
        ->assertJsonFragment(['status' => 'inactive']);
});

it('denies updating a branch owned by another hospital', function () {
    $group = HospitalGroup::create(['name' => 'Other Group']);
    $hospital = Hospital::create([
        'hospital_group_id' => $group->id,
        'name' => 'Other Hospital',
        'code' => 'OTHER',
        'status' => 'active',
    ]);
    $branch = Branch::create([
        'hospital_id' => $hospital->id,
        'name' => 'Other Branch',
        'code' => 'OTHERMAIN',
        'facility_type' => 'hospital',
        'timezone' => 'Asia/Kolkata',
        'status' => 'active',
    ]);

    $response = $this->putJson("/api/v1/admin/branches/{$branch->id}", [
        'name' => 'Illegal Update',
        'code' => 'OTHERMAIN',
        'facility_type' => 'hospital',
        'timezone' => 'Asia/Kolkata',
        'status' => 'active',
    ]);

    $response->assertNotFound();
});

it('lists tenant users and roles for administration', function () {
    $this->getJson('/api/v1/admin/users')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['email' => 'admin@example.com']);

    $this->getJson('/api/v1/admin/roles')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['name' => 'hospital-admin']);
});

it('updates the resolved hospital and writes an audit entry', function () {
    $hospital = Hospital::where('code', 'DEMOHIMS')->firstOrFail();

    $response = $this->putJson("/api/v1/admin/hospitals/{$hospital->id}", [
        'name' => 'Updated Demo Hospital',
        'legal_name' => 'Updated Demo Hospital Pvt Ltd',
        'registration_number' => 'MH-DEMO-002',
        'gstin' => '27ABCDE1234F1Z6',
        'phone' => '+912212345679',
        'status' => 'active',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Demo Hospital')
        ->assertJsonPath('data.code', 'DEMOHIMS');

    expect(AuditLog::query()->where('event', 'hospital.updated')->exists())->toBeTrue();
});

it('denies updating a hospital outside the resolved tenant', function () {
    $group = HospitalGroup::create(['name' => 'Another Group']);
    $otherHospital = Hospital::create([
        'hospital_group_id' => $group->id,
        'name' => 'Another Hospital',
        'code' => 'ANOTHER',
        'status' => 'active',
    ]);

    $response = $this->putJson("/api/v1/admin/hospitals/{$otherHospital->id}", [
        'name' => 'Illegal Update',
        'status' => 'active',
    ]);

    $response->assertNotFound();
});

it('creates and updates users with role and hospital assignment', function () {
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    $create = $this->postJson('/api/v1/admin/users', [
        'name' => 'New Nurse',
        'email' => 'nurse@example.com',
        'mobile' => '+919900009001',
        'password' => 'password123',
        'status' => 'active',
        'role' => 'reception',
        'branch_id' => $branch->id,
        'department_id' => null,
        'assignment_type' => 'staff',
        'is_default' => true,
    ]);

    $create
        ->assertCreated()
        ->assertJsonPath('message', 'User created')
        ->assertJsonPath('data.email', 'nurse@example.com');

    expect($create->json('data.roles'))->toBe(['reception']);

    $userId = $create->json('data.id');

    expect(UserHospitalBranchAssignment::query()
        ->where('user_id', $userId)
        ->where('hospital_id', Hospital::where('code', 'DEMOHIMS')->firstOrFail()->id)
        ->exists())->toBeTrue();

    $update = $this->putJson("/api/v1/admin/users/{$userId}", [
        'name' => 'Updated Nurse',
        'email' => 'nurse@example.com',
        'mobile' => '+919900009001',
        'status' => 'active',
        'role' => 'doctor',
        'branch_id' => $branch->id,
        'department_id' => null,
        'assignment_type' => 'staff',
        'is_default' => true,
    ]);

    $update
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Nurse');

    expect($update->json('data.roles'))->toBe(['doctor']);

    expect(AuditLog::query()->where('event', 'user.created')->exists())->toBeTrue();
    expect(AuditLog::query()->where('event', 'user.updated')->exists())->toBeTrue();
});

it('prevents assigning the platform-admin role without platform-admin privileges', function () {
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    $response = $this->postJson('/api/v1/admin/users', [
        'name' => 'Sneaky Admin',
        'email' => 'sneaky@example.com',
        'mobile' => '+919900009002',
        'password' => 'password123',
        'status' => 'active',
        'role' => 'platform-admin',
        'branch_id' => $branch->id,
    ]);

    $response->assertForbidden();
});

it('denies updating a user outside the resolved hospital', function () {
    $group = HospitalGroup::create(['name' => 'Outside Group']);
    $otherHospital = Hospital::create([
        'hospital_group_id' => $group->id,
        'name' => 'Outside Hospital',
        'code' => 'OUTSIDE',
        'status' => 'active',
    ]);
    $otherUser = User::create([
        'name' => 'Outsider',
        'email' => 'outsider@example.com',
        'mobile' => '+919900009003',
        'password' => bcrypt('password'),
        'status' => 'active',
    ]);
    UserHospitalBranchAssignment::create([
        'user_id' => $otherUser->id,
        'hospital_id' => $otherHospital->id,
        'assignment_type' => 'staff',
        'is_default' => true,
        'status' => 'active',
        'approved_at' => now(),
    ]);

    $response = $this->putJson("/api/v1/admin/users/{$otherUser->id}", [
        'name' => 'Hacked',
        'email' => 'outsider@example.com',
        'status' => 'active',
        'role' => 'reception',
    ]);

    $response->assertNotFound();
});

it('creates custom roles and syncs permissions on update', function () {
    $create = $this->postJson('/api/v1/admin/roles', [
        'name' => 'ward-nurse',
        'permissions' => ['patients.manage'],
    ]);

    $create
        ->assertCreated()
        ->assertJsonPath('data.name', 'ward-nurse');

    expect($create->json('data.permissions'))->toBe(['patients.manage']);

    $roleId = $create->json('data.id');

    $update = $this->putJson("/api/v1/admin/roles/{$roleId}", [
        'name' => 'ward-nurse',
        'permissions' => ['patients.manage', 'appointments.manage'],
    ]);

    $update->assertOk();

    expect($update->json('data.permissions'))->toEqualCanonicalizing(['patients.manage', 'appointments.manage']);
});

it('prevents hospital admins from assigning permissions they do not have', function () {
    $response = $this->postJson('/api/v1/admin/roles', [
        'name' => 'super-role',
        'permissions' => ['opd.consult'],
    ]);

    $response->assertForbidden();
});

it('prevents renaming system roles', function () {
    $role = Role::where('name', 'reception')->firstOrFail();

    $response = $this->putJson("/api/v1/admin/roles/{$role->id}", [
        'name' => 'renamed-reception',
        'permissions' => ['patients.manage'],
    ]);

    $response->assertUnprocessable();
});

it('prevents non platform-admins from touching the platform-admin role', function () {
    $role = Role::where('name', 'platform-admin')->firstOrFail();

    $response = $this->putJson("/api/v1/admin/roles/{$role->id}", [
        'name' => 'platform-admin',
        'permissions' => ['patients.manage'],
    ]);

    $response->assertForbidden();
});

it('lists all permissions for administration UI', function () {
    $response = $this->getJson('/api/v1/admin/permissions');

    $response
        ->assertOk()
        ->assertJsonFragment(['name' => 'patients.manage']);
});
