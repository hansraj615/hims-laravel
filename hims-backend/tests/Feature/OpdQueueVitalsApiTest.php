<?php

use App\Domain\Appointments\Models\Appointment;
use App\Domain\EMR\Models\Encounter;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Department;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\OPD\Models\OpdQueue;
use App\Domain\Patients\Models\Patient;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
});

it('allows a nurse to record queue vitals and copies them into the consultation', function () {
    $nurse = User::where('email', 'nurse@example.com')->firstOrFail();
    $reception = User::where('email', 'reception@example.com')->firstOrFail();
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();
    $patient = createVitalsTestPatient();

    Sanctum::actingAs($reception);

    $appointmentId = $this->postJson('/api/v1/appointments', [
        'patient_id' => $patient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
        'appointment_date' => now()->toDateString(),
        'slot_start' => '10:00',
        'slot_end' => '10:30',
        'visit_type' => 'first_visit',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/appointments/{$appointmentId}/check-in")->assertOk();
    $queueId = OpdQueue::where('appointment_id', $appointmentId)->firstOrFail()->id;

    Sanctum::actingAs($nurse);

    $this->getJson('/api/v1/opd/queue')->assertOk()->assertJsonFragment(['token_code' => 'GENMED-001']);

    $this->putJson("/api/v1/opd/queue/{$queueId}/vitals", [
        'vitals' => [
            'temperature_c' => 37.2,
            'pulse_bpm' => 78,
            'bp_systolic' => 120,
            'bp_diastolic' => 80,
            'spo2_percent' => 98,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.has_vitals', true)
        ->assertJsonPath('data.vitals.pulse_bpm', 78);

    $this->postJson("/api/v1/opd/queue/{$queueId}/call")->assertForbidden();

    Sanctum::actingAs($doctor);

    $encounterId = $this->postJson('/api/v1/opd/consultations', [
        'opd_queue_id' => $queueId,
    ])->assertCreated()->json('data.id');

    $encounter = Encounter::findOrFail($encounterId);
    expect($encounter->vitals['pulse_bpm'])->toBe(78)
        ->and($encounter->vitals['temperature_c'])->toBe(37.2);
});

it('denies reception from writing vitals without opd.vitals', function () {
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();
    $patient = createVitalsTestPatient(['mobile' => '9900004101']);

    $appointmentId = $this->postJson('/api/v1/appointments', [
        'patient_id' => $patient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
        'appointment_date' => now()->toDateString(),
        'slot_start' => '10:30',
        'slot_end' => '11:00',
        'visit_type' => 'first_visit',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/appointments/{$appointmentId}/check-in")->assertOk();
    $queueId = OpdQueue::where('appointment_id', $appointmentId)->value('id');

    $this->putJson("/api/v1/opd/queue/{$queueId}/vitals", [
        'vitals' => ['pulse_bpm' => 80],
    ])->assertForbidden();
});

function createVitalsTestPatient(array $overrides = []): Patient
{
    $hospital = Hospital::where('code', 'DEMOHIMS')->firstOrFail();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    return Patient::create([
        'hospital_id' => $hospital->id,
        'branch_id' => $branch->id,
        'uhid' => 'DEMOHIMS-'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
        'first_name' => 'Vitals',
        'last_name' => 'Patient',
        'gender' => 'unknown',
        'mobile' => '9900004100',
        'status' => 'active',
        ...$overrides,
    ]);
}
