<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Hospitals\Models\HospitalGroup;
use App\Domain\Patients\Models\Patient;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
});

it('registers patients with tenant generated UHID values', function () {
    $response = $this->postJson('/api/v1/patients', patientPayload([
        'first_name' => 'Anita',
        'last_name' => 'Rao',
        'mobile' => '9900001001',
    ]));

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Patient registered')
        ->assertJsonPath('data.uhid', 'DEMOHIMS-000001')
        ->assertJsonPath('data.mobile', '+919900001001')
        ->assertJsonPath('data.full_name', 'Anita Rao');

    expect(Patient::where('uhid', 'DEMOHIMS-000001')->firstOrFail()->hospital_id)
        ->toBe(Hospital::where('code', 'DEMOHIMS')->firstOrFail()->id);
});

it('finds duplicate candidates by mobile number before registration', function () {
    $this->postJson('/api/v1/patients', patientPayload([
        'first_name' => 'Duplicate',
        'last_name' => 'Patient',
        'mobile' => '9900001002',
    ]))->assertCreated();

    $response = $this->getJson('/api/v1/patients/duplicates?mobile=9900001002&name=Different');

    $response
        ->assertOk()
        ->assertJsonPath('data.0.mobile', '+919900001002')
        ->assertJsonPath('data.0.full_name', 'Duplicate Patient');
});

it('updates patient demographics without changing UHID', function () {
    $patientId = $this->postJson('/api/v1/patients', patientPayload([
        'first_name' => 'Old',
        'last_name' => 'Name',
    ]))->json('data.id');

    $response = $this->putJson("/api/v1/patients/{$patientId}", patientPayload([
        'first_name' => 'New',
        'last_name' => 'Name',
        'city' => 'Bengaluru',
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('data.uhid', 'DEMOHIMS-000001')
        ->assertJsonPath('data.full_name', 'New Name')
        ->assertJsonPath('data.city', 'Bengaluru');
});

it('does not expose patients from another hospital tenant', function () {
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
    Patient::create([
        'hospital_id' => $externalHospital->id,
        'branch_id' => $externalBranch->id,
        'uhid' => 'EXT-000001',
        'first_name' => 'External',
        'gender' => 'unknown',
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/v1/patients');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('uhid'))->not->toContain('EXT-000001');
});

it('denies access when the user lacks patient permissions', function () {
    Sanctum::actingAs(User::where('email', 'doctor@example.com')->firstOrFail());

    $this->getJson('/api/v1/patients')
        ->assertForbidden();
});

it('adds and lists metadata-only documents for a patient', function () {
    $patientId = $this->postJson('/api/v1/patients', patientPayload([
        'first_name' => 'Doc',
        'last_name' => 'Holder',
        'mobile' => '9900001003',
    ]))->json('data.id');

    $response = $this->postJson("/api/v1/patients/{$patientId}/documents", [
        'document_type' => 'lab_report',
        'title' => 'CBC Report',
        'file_path' => 'documents/cbc-report.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 20480,
        'metadata' => ['pages' => 2],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Patient document added')
        ->assertJsonPath('data.title', 'CBC Report')
        ->assertJsonPath('data.document_type', 'lab_report');

    expect(AuditLog::query()->where('event', 'patient.document.created')->exists())->toBeTrue();

    $list = $this->getJson("/api/v1/patients/{$patientId}/documents");

    $list
        ->assertOk()
        ->assertJsonFragment(['title' => 'CBC Report']);
});

it('denies access to documents of a patient from another hospital', function () {
    $externalGroup = HospitalGroup::create(['name' => 'Doc External Group']);
    $externalHospital = Hospital::create([
        'hospital_group_id' => $externalGroup->id,
        'name' => 'Doc External Hospital',
        'code' => 'DOCEXT',
        'status' => 'active',
    ]);
    $externalPatient = Patient::create([
        'hospital_id' => $externalHospital->id,
        'uhid' => 'DOCEXT-000001',
        'first_name' => 'External',
        'gender' => 'unknown',
        'status' => 'active',
    ]);

    $this->getJson("/api/v1/patients/{$externalPatient->id}/documents")->assertNotFound();
});

function patientPayload(array $overrides = []): array
{
    return [
        'branch_id' => Branch::where('code', 'MAIN')->firstOrFail()->id,
        'salutation' => null,
        'patient_category' => 'general',
        'registration_source' => 'walk_in',
        'referred_by' => null,
        'first_name' => 'Test',
        'middle_name' => null,
        'last_name' => 'Patient',
        'gender' => 'unknown',
        'blood_group' => 'unknown',
        'marital_status' => 'unknown',
        'occupation' => null,
        'nationality' => 'Indian',
        'preferred_language' => 'English',
        'date_of_birth' => null,
        'age_years' => 35,
        'age_months' => null,
        'age_days' => null,
        'mobile' => '9900001999',
        'alternate_mobile' => null,
        'email' => null,
        'address' => null,
        'city' => null,
        'district' => null,
        'state' => null,
        'pincode' => null,
        'country' => 'India',
        'identity_type' => null,
        'identity_number' => null,
        'abha_id' => null,
        'guardian_name' => null,
        'guardian_relation' => null,
        'guardian_mobile' => null,
        'emergency_contact_name' => null,
        'emergency_contact_mobile' => null,
        'emergency_contact_relation' => null,
        'consent_sms' => true,
        'consent_email' => false,
        'consent_whatsapp' => false,
        'remarks' => null,
        'status' => 'active',
        ...$overrides,
    ];
}
