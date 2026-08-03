<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
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

it('writes a hash chained audit log when a patient is created', function () {
    $response = $this->postJson('/api/v1/patients', auditPatientPayload([
        'first_name' => 'Audit',
        'last_name' => 'Subject',
        'mobile' => '9900002001',
    ]));

    $response->assertCreated();

    $patientId = $response->json('data.id');

    $log = AuditLog::query()->where('module', 'patients')->where('event', 'patient.created')->firstOrFail();

    expect($log->hospital_id)->toBe(Hospital::where('code', 'DEMOHIMS')->firstOrFail()->id);
    expect($log->auditable_type)->toBe(Patient::class);
    expect($log->auditable_id)->toBe($patientId);
    expect($log->hash)->not->toBeNull();
    expect($log->previous_hash)->toBeNull();
    expect($log->user_id)->toBe(User::where('email', 'reception@example.com')->firstOrFail()->id);
});

it('chains audit log hashes for a hospital across successive events', function () {
    $first = $this->postJson('/api/v1/patients', auditPatientPayload([
        'first_name' => 'First',
        'last_name' => 'Patient',
        'mobile' => '9900002002',
    ]))->json('data.id');

    $this->putJson("/api/v1/patients/{$first}", auditPatientPayload([
        'first_name' => 'First',
        'last_name' => 'Updated',
        'mobile' => '9900002002',
    ]))->assertOk();

    $logs = AuditLog::query()->orderBy('id')->get();

    expect($logs)->toHaveCount(2);
    expect($logs[0]->previous_hash)->toBeNull();
    expect($logs[1]->previous_hash)->toBe($logs[0]->hash);
    expect($logs[1]->hash)->not->toBe($logs[0]->hash);
});

function auditPatientPayload(array $overrides = []): array
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
