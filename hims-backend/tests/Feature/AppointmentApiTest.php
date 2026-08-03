<?php

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Department;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Hospitals\Models\HospitalGroup;
use App\Domain\OPD\Models\OpdQueue;
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

it('books an appointment with a hospital generated appointment number', function () {
    $patient = createTestPatient();
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();

    $response = $this->postJson('/api/v1/appointments', appointmentPayload([
        'patient_id' => $patient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
    ]));

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Appointment booked')
        ->assertJsonPath('data.status', 'booked')
        ->assertJsonPath('data.appointment_number', 'DEMOHIMS-APT-000001')
        ->assertJsonPath('data.patient.uhid', $patient->uhid)
        ->assertJsonPath('data.doctor.id', $doctor->id)
        ->assertJsonPath('data.department.code', 'GENMED');

    expect(Appointment::where('appointment_number', 'DEMOHIMS-APT-000001')->firstOrFail()->hospital_id)
        ->toBe(Hospital::where('code', 'DEMOHIMS')->firstOrFail()->id);
});

it('exposes department and doctor options to reception for booking', function () {
    $this->getJson('/api/v1/appointments/options')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['code' => 'GENMED'])
        ->assertJsonFragment(['email' => 'doctor@example.com']);
});

it('prevents check-in once an appointment has been cancelled', function () {
    $patient = createTestPatient();

    $appointmentId = $this->postJson('/api/v1/appointments', appointmentPayload([
        'patient_id' => $patient->id,
    ]))->json('data.id');

    $this->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
        'cancellation_reason' => 'Patient requested reschedule',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->postJson("/api/v1/appointments/{$appointmentId}/check-in")
        ->assertStatus(422);

    expect(OpdQueue::where('appointment_id', $appointmentId)->exists())->toBeFalse();
});

it('creates an OPD queue token when a patient is checked in', function () {
    $patient = createTestPatient();
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();

    $appointmentId = $this->postJson('/api/v1/appointments', appointmentPayload([
        'patient_id' => $patient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
    ]))->json('data.id');

    $response = $this->postJson("/api/v1/appointments/{$appointmentId}/check-in");

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'checked_in');

    $queue = OpdQueue::where('appointment_id', $appointmentId)->firstOrFail();

    expect($queue->token_number)->toBe(1)
        ->and($queue->token_code)->toBe('GENMED-001')
        ->and($queue->status)->toBe('waiting')
        ->and($queue->patient_id)->toBe($patient->id);

    // Re-checking-in is refused once already checked in.
    $this->postJson("/api/v1/appointments/{$appointmentId}/check-in")->assertStatus(422);
});

it('allocates sequential tokens for the same doctor and date across concurrent-style check-ins', function () {
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();

    $firstPatient = createTestPatient(['mobile' => '9900002001']);
    $secondPatient = createTestPatient(['mobile' => '9900002002']);

    $firstAppointmentId = $this->postJson('/api/v1/appointments', appointmentPayload([
        'patient_id' => $firstPatient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
        'slot_start' => '10:00',
        'slot_end' => '10:30',
    ]))->json('data.id');

    $secondAppointmentId = $this->postJson('/api/v1/appointments', appointmentPayload([
        'patient_id' => $secondPatient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
        'slot_start' => '10:30',
        'slot_end' => '11:00',
    ]))->json('data.id');

    $this->postJson("/api/v1/appointments/{$firstAppointmentId}/check-in")->assertOk();
    $this->postJson("/api/v1/appointments/{$secondAppointmentId}/check-in")->assertOk();

    $firstToken = OpdQueue::where('appointment_id', $firstAppointmentId)->firstOrFail()->token_number;
    $secondToken = OpdQueue::where('appointment_id', $secondAppointmentId)->firstOrFail()->token_number;

    expect($firstToken)->toBe(1);
    expect($secondToken)->toBe(2);
});

it('does not expose appointments from another hospital tenant', function () {
    $externalGroup = HospitalGroup::create(['name' => 'External Appointments Group']);
    $externalHospital = Hospital::create([
        'hospital_group_id' => $externalGroup->id,
        'name' => 'External Appointments Hospital',
        'code' => 'EXTAPT',
        'status' => 'active',
    ]);
    $externalBranch = Branch::create([
        'hospital_id' => $externalHospital->id,
        'name' => 'External Branch',
        'code' => 'EXTAPTMAIN',
        'facility_type' => 'clinic',
        'timezone' => 'Asia/Kolkata',
        'status' => 'active',
    ]);
    $externalPatient = Patient::create([
        'hospital_id' => $externalHospital->id,
        'branch_id' => $externalBranch->id,
        'uhid' => 'EXTAPT-000001',
        'first_name' => 'External',
        'gender' => 'unknown',
        'status' => 'active',
    ]);
    $externalAppointment = Appointment::create([
        'hospital_id' => $externalHospital->id,
        'branch_id' => $externalBranch->id,
        'patient_id' => $externalPatient->id,
        'appointment_number' => 'EXTAPT-APT-000001',
        'appointment_date' => now()->toDateString(),
        'status' => 'booked',
    ]);

    $index = $this->getJson('/api/v1/appointments');
    $index->assertOk();
    expect(collect($index->json('data'))->pluck('appointment_number'))->not->toContain('EXTAPT-APT-000001');

    $this->getJson("/api/v1/appointments/{$externalAppointment->id}")->assertNotFound();
});

it('allows a doctor to load the OPD queue via the opd.consult permission', function () {
    $patient = createTestPatient();
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();

    $appointmentId = $this->postJson('/api/v1/appointments', appointmentPayload([
        'patient_id' => $patient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
    ]))->json('data.id');

    $this->postJson("/api/v1/appointments/{$appointmentId}/check-in")->assertOk();

    Sanctum::actingAs($doctor);

    $response = $this->getJson('/api/v1/opd/queue');

    $response
        ->assertOk()
        ->assertJsonFragment(['token_code' => 'GENMED-001']);
});

it('enforces queue status progression through call, start and complete', function () {
    $patient = createTestPatient();
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();

    $appointmentId = $this->postJson('/api/v1/appointments', appointmentPayload([
        'patient_id' => $patient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
    ]))->json('data.id');

    $this->postJson("/api/v1/appointments/{$appointmentId}/check-in")->assertOk();

    $opdQueueId = OpdQueue::where('appointment_id', $appointmentId)->firstOrFail()->id;

    $this->postJson("/api/v1/opd/queue/{$opdQueueId}/call")->assertOk();
    $this->postJson("/api/v1/opd/queue/{$opdQueueId}/start")->assertOk();
    $this->postJson("/api/v1/opd/queue/{$opdQueueId}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    // Cannot skip straight from completed back into an earlier stage.
    $this->postJson("/api/v1/opd/queue/{$opdQueueId}/start")->assertStatus(422);
});

it('denies booking appointments for a user lacking appointments.manage', function () {
    Sanctum::actingAs(User::where('email', 'doctor@example.com')->firstOrFail());

    $this->postJson('/api/v1/appointments', appointmentPayload([
        'patient_id' => createTestPatient()->id,
    ]))->assertForbidden();
});

function appointmentPayload(array $overrides = []): array
{
    return [
        'branch_id' => Branch::where('code', 'MAIN')->firstOrFail()->id,
        'appointment_date' => now()->toDateString(),
        'slot_start' => '10:00',
        'slot_end' => '10:30',
        'visit_type' => 'first_visit',
        'source' => 'walk_in',
        'priority' => 'normal',
        'fee_amount' => 500,
        'reason' => 'General consultation',
        ...$overrides,
    ];
}

function createTestPatient(array $overrides = []): Patient
{
    $hospital = Hospital::where('code', 'DEMOHIMS')->firstOrFail();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    return Patient::create([
        'hospital_id' => $hospital->id,
        'branch_id' => $branch->id,
        'uhid' => 'DEMOHIMS-'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
        'first_name' => 'Test',
        'last_name' => 'Patient',
        'gender' => 'unknown',
        'mobile' => '9900001999',
        'status' => 'active',
        ...$overrides,
    ]);
}
