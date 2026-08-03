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
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
});

it('skips a called token and can requeue it to waiting', function () {
    $patient = createTestPatient(['mobile' => '9900007101']);
    $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
    $department = Department::where('code', 'GENMED')->firstOrFail();

    $appointmentId = $this->postJson('/api/v1/appointments', appointmentPayload([
        'patient_id' => $patient->id,
        'doctor_user_id' => $doctor->id,
        'department_id' => $department->id,
        'slot_start' => '09:00',
        'slot_end' => '09:30',
    ]))->assertCreated()->json('data.id');

    $this->postJson("/api/v1/appointments/{$appointmentId}/check-in")->assertOk();
    $queueId = OpdQueue::where('appointment_id', $appointmentId)->value('id');

    $this->postJson("/api/v1/opd/queue/{$queueId}/call")->assertOk();
    $this->postJson("/api/v1/opd/queue/{$queueId}/skip")
        ->assertOk()
        ->assertJsonPath('data.status', 'skipped');

    $this->postJson("/api/v1/opd/queue/{$queueId}/requeue")
        ->assertOk()
        ->assertJsonPath('data.status', 'waiting');
});

it('filters service catalog by owner billing category', function () {
    Sanctum::actingAs(User::where('email', 'billing@example.com')->firstOrFail());

    $this->getJson('/api/v1/billing/services?category=consultant_fee')
        ->assertOk()
        ->assertJsonFragment(['code' => 'OPDCONSULT', 'category' => 'consultant_fee']);

    $this->getJson('/api/v1/billing/services?category=pathology')
        ->assertOk()
        ->assertJsonFragment(['code' => 'CBC', 'category' => 'pathology']);
});
