<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\EMR\Models\Encounter;
use App\Domain\Hospitals\Models\Department;
use App\Domain\OPD\Models\OpdQueue;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
});

function checkInPatientForConsultation(array $overrides = []): array
{
    $patient = createTestPatient($overrides);
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();

    $appointmentId = test()->postJson('/api/v1/appointments', appointmentPayload([
        'patient_id' => $patient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
    ]))->json('data.id');

    test()->postJson("/api/v1/appointments/{$appointmentId}/check-in")->assertOk();

    $queue = OpdQueue::where('appointment_id', $appointmentId)->firstOrFail();

    return [
        'patient' => $patient,
        'doctor' => $doctor,
        'appointmentId' => $appointmentId,
        'queue' => $queue,
    ];
}

it('starts a draft consultation from an OPD queue entry and moves the queue into consultation', function () {
    ['doctor' => $doctor, 'queue' => $queue] = checkInPatientForConsultation(['mobile' => '9900003001']);

    Sanctum::actingAs($doctor);

    $response = $this->postJson('/api/v1/opd/consultations', ['opd_queue_id' => $queue->id]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.encounter_number', 'DEMOHIMS-ENC-000001')
        ->assertJsonPath('data.doctor.id', $doctor->id);

    expect($queue->refresh()->status)->toBe('in_consultation');
});

it('updates a draft consultation with vitals, diagnoses and prescription items', function () {
    ['doctor' => $doctor, 'queue' => $queue] = checkInPatientForConsultation(['mobile' => '9900003002']);

    Sanctum::actingAs($doctor);

    $encounterId = $this->postJson('/api/v1/opd/consultations', ['opd_queue_id' => $queue->id])->json('data.id');

    $response = $this->putJson("/api/v1/opd/consultations/{$encounterId}", [
        'vitals' => ['bp' => '120/80', 'pulse' => 78, 'temperature' => 98.6],
        'chief_complaints' => ['Fever', 'Headache'],
        'clinical_history' => ['No known allergies'],
        'examination' => ['General condition stable'],
        'diagnoses' => [
            ['display' => 'Viral fever', 'system' => 'ICD-10', 'code' => 'B34.9', 'type' => 'provisional'],
        ],
        'care_plan' => ['Rest and hydration'],
        'follow_up' => ['After 3 days if not improved'],
        'prescription_items' => [
            [
                'medicine_name' => 'Paracetamol',
                'strength' => '500mg',
                'route' => 'oral',
                'frequency' => 'TDS',
                'duration' => '3 days',
                'quantity' => 9,
                'instructions' => 'After food',
            ],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.diagnoses.0.display', 'Viral fever')
        ->assertJsonPath('data.prescriptions.0.status', 'draft')
        ->assertJsonPath('data.prescriptions.0.items.0.medicine_name', 'Paracetamol');

    $encounter = Encounter::findOrFail($encounterId);
    expect($encounter->prescriptions()->firstOrFail()->items()->count())->toBe(1);

    // Re-submitting replaces the item set rather than accumulating duplicates.
    $this->putJson("/api/v1/opd/consultations/{$encounterId}", [
        'prescription_items' => [
            ['medicine_name' => 'Cetirizine', 'frequency' => 'OD', 'duration' => '5 days'],
        ],
    ])->assertOk();

    expect($encounter->prescriptions()->firstOrFail()->items()->count())->toBe(1)
        ->and($encounter->prescriptions()->firstOrFail()->items()->firstOrFail()->medicine_name)->toBe('Cetirizine');
});

it('completes a consultation, builds an FHIR payload and creates a draft invoice from the appointment fee', function () {
    ['queue' => $queue, 'doctor' => $doctor] = checkInPatientForConsultation(['mobile' => '9900003003']);

    Sanctum::actingAs($doctor);

    $encounterId = $this->postJson('/api/v1/opd/consultations', ['opd_queue_id' => $queue->id])->json('data.id');

    $this->putJson("/api/v1/opd/consultations/{$encounterId}", [
        'diagnoses' => [['display' => 'Common cold']],
        'prescription_items' => [
            ['medicine_name' => 'Cetirizine', 'frequency' => 'OD', 'duration' => '5 days'],
        ],
    ])->assertOk();

    $response = $this->postJson("/api/v1/opd/consultations/{$encounterId}/complete");

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.fhir_payload.resourceType', 'Bundle')
        ->assertJsonPath('data.prescriptions.0.status', 'issued');

    expect($queue->refresh()->status)->toBe('completed');

    $invoice = Invoice::where('encounter_id', $encounterId)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe('draft')
        ->and((float) $invoice->grand_total)->toBe(500.0)
        ->and((float) $invoice->balance_total)->toBe(500.0);
});

it('prevents editing or completing a consultation that is already completed', function () {
    ['queue' => $queue, 'doctor' => $doctor] = checkInPatientForConsultation(['mobile' => '9900003004']);

    Sanctum::actingAs($doctor);

    $encounterId = $this->postJson('/api/v1/opd/consultations', ['opd_queue_id' => $queue->id])->json('data.id');
    $this->postJson("/api/v1/opd/consultations/{$encounterId}/complete")->assertOk();

    $this->putJson("/api/v1/opd/consultations/{$encounterId}", ['chief_complaints' => ['Should fail']])
        ->assertStatus(422);

    $this->postJson("/api/v1/opd/consultations/{$encounterId}/complete")
        ->assertStatus(422);
});

it('denies starting a consultation for a user lacking opd.consult', function () {
    ['queue' => $queue] = checkInPatientForConsultation(['mobile' => '9900003005']);

    Sanctum::actingAs(User::where('email', 'billing@example.com')->firstOrFail());

    $this->postJson('/api/v1/opd/consultations', ['opd_queue_id' => $queue->id])
        ->assertForbidden();
});
