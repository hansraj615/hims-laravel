<?php

use App\Domain\Appointments\Models\DoctorFeeMaster;
use App\Domain\Appointments\Models\DoctorLeave;
use App\Domain\Appointments\Models\DoctorSchedule;
use App\Domain\Hospitals\Models\Department;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
});

it('lets hospital admin manage doctor schedules leaves and fees', function () {
    Sanctum::actingAs(User::where('email', 'admin@example.com')->firstOrFail());
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();

    $this->getJson("/api/v1/admin/doctors/{$doctor->id}/schedules")
        ->assertOk()
        ->assertJsonPath('success', true);

    $created = $this->postJson("/api/v1/admin/doctors/{$doctor->id}/schedules", [
        'day_of_week' => 1,
        'start_time' => '18:00',
        'end_time' => '19:00',
        'slot_duration_minutes' => 20,
    ])->assertCreated();

    $scheduleId = $created->json('data.id');

    $this->putJson("/api/v1/admin/doctors/{$doctor->id}/schedules/{$scheduleId}", [
        'day_of_week' => 1,
        'start_time' => '18:00',
        'end_time' => '19:00',
        'slot_duration_minutes' => 15,
        'status' => 'active',
    ])->assertOk()->assertJsonPath('data.slot_duration_minutes', 15);

    $this->postJson("/api/v1/admin/doctors/{$doctor->id}/leaves", [
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(11)->toDateString(),
        'reason' => 'Conference',
    ])->assertCreated()->assertJsonPath('data.reason', 'Conference');

    $this->putJson("/api/v1/admin/doctors/{$doctor->id}/fees", [
        'visit_type' => 'first_visit',
        'fee_amount' => 650,
    ])->assertOk()->assertJsonPath('data.fee_amount', '650.00');

    expect(DoctorFeeMaster::where('doctor_user_id', $doctor->id)->where('visit_type', 'first_visit')->value('fee_amount'))
        ->toBe('650.00');
});

it('returns available slots and auto applies consultant fee on booking', function () {
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();
    $patient = createScheduleTestPatient();

    $slots = $this->getJson('/api/v1/appointments/slots?'.http_build_query([
        'doctor_user_id' => $doctor->id,
        'date' => now()->toDateString(),
        'visit_type' => 'first_visit',
    ]))->assertOk();

    expect($slots->json('data.on_leave'))->toBeFalse();
    expect($slots->json('data.fee_amount'))->toBe('500.00');
    expect(collect($slots->json('data.slots'))->pluck('slot_start'))->toContain('10:00');

    $this->postJson('/api/v1/appointments', [
        'patient_id' => $patient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
        'appointment_date' => now()->toDateString(),
        'slot_start' => '10:00',
        'slot_end' => '10:30',
        'visit_type' => 'first_visit',
        'source' => 'walk_in',
    ])
        ->assertCreated()
        ->assertJsonPath('data.fee_amount', '500.00')
        ->assertJsonPath('data.slot_start', '10:00');
});

it('blocks booking when the doctor is on leave', function () {
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();
    $patient = createScheduleTestPatient(['mobile' => '9900003001']);

    DoctorLeave::create([
        'hospital_id' => $doctor->hospitalAssignments()->firstOrFail()->hospital_id,
        'doctor_user_id' => $doctor->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
        'reason' => 'Personal leave',
        'status' => 'active',
    ]);

    $this->getJson('/api/v1/appointments/slots?'.http_build_query([
        'doctor_user_id' => $doctor->id,
        'date' => now()->toDateString(),
    ]))
        ->assertOk()
        ->assertJsonPath('data.on_leave', true);

    $this->postJson('/api/v1/appointments', [
        'patient_id' => $patient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
        'appointment_date' => now()->toDateString(),
        'slot_start' => '10:00',
        'slot_end' => '10:30',
        'visit_type' => 'first_visit',
    ])->assertStatus(422)->assertJsonValidationErrors(['appointment_date']);
});

it('rejects double booking of the same slot', function () {
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();

    $payload = [
        'patient_id' => createScheduleTestPatient(['mobile' => '9900003002'])->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
        'appointment_date' => now()->toDateString(),
        'slot_start' => '11:00',
        'slot_end' => '11:30',
        'visit_type' => 'first_visit',
    ];

    $this->postJson('/api/v1/appointments', $payload)->assertCreated();

    $this->postJson('/api/v1/appointments', [
        ...$payload,
        'patient_id' => createScheduleTestPatient(['mobile' => '9900003003'])->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['slot_start']);
});

it('requires a schedule slot when the doctor has schedules configured', function () {
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();

    expect(DoctorSchedule::where('doctor_user_id', $doctor->id)->exists())->toBeTrue();

    $this->postJson('/api/v1/appointments', [
        'patient_id' => createScheduleTestPatient(['mobile' => '9900003004'])->id,
        'doctor_user_id' => $doctor->id,
        'appointment_date' => now()->toDateString(),
        'visit_type' => 'first_visit',
    ])->assertStatus(422)->assertJsonValidationErrors(['slot_start']);
});

function createScheduleTestPatient(array $overrides = []): \App\Domain\Patients\Models\Patient
{
    $hospital = \App\Domain\Hospitals\Models\Hospital::where('code', 'DEMOHIMS')->firstOrFail();
    $branch = \App\Domain\Hospitals\Models\Branch::where('code', 'MAIN')->firstOrFail();

    return \App\Domain\Patients\Models\Patient::create([
        'hospital_id' => $hospital->id,
        'branch_id' => $branch->id,
        'uhid' => 'DEMOHIMS-'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
        'first_name' => 'Slot',
        'last_name' => 'Patient',
        'gender' => 'unknown',
        'mobile' => '9900003099',
        'status' => 'active',
        ...$overrides,
    ]);
}
