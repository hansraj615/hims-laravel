<?php

use App\Domain\Hospitals\Models\Department;
use App\Domain\OPD\Models\OpdQueue;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
});

it('returns prior completed encounters for a doctor without patients.manage', function () {
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());

    $patient = createTestPatient(['mobile' => '9900005101']);
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();

    $firstQueue = bookAndCheckIn($patient->id, $doctor->id, $department->id, '09:00', '09:30');

    Sanctum::actingAs($doctor);

    $firstEncounterId = $this->postJson('/api/v1/opd/consultations', ['opd_queue_id' => $firstQueue->id])
        ->assertCreated()
        ->json('data.id');

    $this->putJson("/api/v1/opd/consultations/{$firstEncounterId}", [
        'vitals' => ['temperature_c' => 37.5, 'pulse_bpm' => 90, 'bp_systolic' => 118, 'bp_diastolic' => 76],
        'chief_complaints' => ['Fever'],
        'diagnoses' => [['display' => 'Viral fever', 'code' => 'B34.9', 'system' => 'ICD-10']],
        'care_plan' => ['notes' => 'Rest and fluids'],
        'prescription_items' => [
            [
                'medicine_name' => 'Paracetamol',
                'strength' => '500mg',
                'route' => 'oral',
                'frequency' => 'TDS',
                'duration' => '3 days',
            ],
        ],
    ])->assertOk();

    $this->postJson("/api/v1/opd/consultations/{$firstEncounterId}/complete")->assertOk();

    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
    $secondQueue = bookAndCheckIn($patient->id, $doctor->id, $department->id, '10:00', '10:30');

    Sanctum::actingAs($doctor);
    $currentEncounterId = $this->postJson('/api/v1/opd/consultations', ['opd_queue_id' => $secondQueue->id])
        ->assertCreated()
        ->json('data.id');

    $history = $this->getJson("/api/v1/patients/{$patient->id}/clinical-history?".http_build_query([
        'exclude_encounter_id' => $currentEncounterId,
    ]));

    $history
        ->assertOk()
        ->assertJsonPath('data.patient_id', $patient->id)
        ->assertJsonPath('data.encounters.0.encounter_number', 'DEMOHIMS-ENC-000001')
        ->assertJsonPath('data.encounters.0.diagnoses.0.display', 'Viral fever')
        ->assertJsonPath('data.encounters.0.prescription_items.0.medicine_name', 'Paracetamol')
        ->assertJsonPath('data.encounters.0.vitals_summary.pulse_bpm', 90)
        ->assertJsonPath('meta.count', 1);

    expect(collect($history->json('data.encounters'))->pluck('id'))->not->toContain($currentEncounterId);
});

it('denies nurse from viewing clinical history', function () {
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
    $patient = createTestPatient(['mobile' => '9900005102']);

    Sanctum::actingAs(User::where('email', 'nurse@example.com')->firstOrFail());

    $this->getJson("/api/v1/patients/{$patient->id}/clinical-history")->assertForbidden();
});

function bookAndCheckIn(int $patientId, int $doctorId, int $departmentId, string $start, string $end): OpdQueue
{
    $appointmentId = test()->postJson('/api/v1/appointments', appointmentPayload([
        'patient_id' => $patientId,
        'doctor_user_id' => $doctorId,
        'department_id' => $departmentId,
        'slot_start' => $start,
        'slot_end' => $end,
    ]))->assertCreated()->json('data.id');

    test()->postJson("/api/v1/appointments/{$appointmentId}/check-in")->assertOk();

    return OpdQueue::where('appointment_id', $appointmentId)->firstOrFail();
}
