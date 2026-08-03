<?php

use App\Domain\Notifications\Models\NotificationLog;
use App\Domain\OPD\Models\OpdQueue;
use App\Domain\Hospitals\Models\Department;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
});

it('emails a prescription when consult completes for a consented patient', function () {
    Mail::fake();

    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
    $patient = createTestPatient([
        'mobile' => '9900006101',
        'email' => 'patient.rx@example.com',
        'consent_email' => true,
    ]);
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

    Sanctum::actingAs($doctor);
    $encounterId = $this->postJson('/api/v1/opd/consultations', ['opd_queue_id' => $queueId])
        ->assertCreated()
        ->json('data.id');

    $this->putJson("/api/v1/opd/consultations/{$encounterId}", [
        'diagnoses' => [['display' => 'Common cold']],
        'prescription_items' => [
            [
                'medicine_name' => 'Cetirizine',
                'strength' => '10mg',
                'frequency' => 'OD',
                'duration' => '5 days',
            ],
        ],
    ])->assertOk();

    $this->postJson("/api/v1/opd/consultations/{$encounterId}/complete")->assertOk();

    Mail::assertSent(\App\Mail\TemplatedNotificationMail::class, 1);

    $log = NotificationLog::query()->where('template_code', 'prescription.ready')->where('channel', 'email')->first();
    expect($log)->not->toBeNull()
        ->and($log->recipient)->toBe('patient.rx@example.com')
        ->and($log->status)->toBe('sent')
        ->and($log->body)->toContain('Cetirizine');
});

it('leaves SMS notifications pending when credentials are not configured', function () {
    $dispatcher = app(\App\Domain\Notifications\Services\NotificationDispatcher::class);

    NotificationTemplateEnsure('payment.received', 'sms');

    $log = $dispatcher->dispatch(
        hospitalId: \App\Domain\Hospitals\Models\Hospital::where('code', 'DEMOHIMS')->value('id'),
        branchId: null,
        templateCode: 'payment.received',
        channel: 'sms',
        recipient: '+919900000099',
        context: ['amount' => '100', 'invoice_number' => 'INV-1', 'receipt_number' => 'R-1'],
    );

    expect($log->status)->toBe('pending')
        ->and(data_get($log->provider_response, 'reason'))->toBe('credentials_not_configured');
});

it('emails a generated OTP when demo OTP is disabled', function () {
    Mail::fake();
    config([
        'hims.auth.demo_otp_enabled' => false,
        'hims.auth.otp_length' => 6,
    ]);

    $user = User::where('email', 'reception@example.com')->firstOrFail();

    app(\App\Domain\Users\Services\OtpLoginService::class)->createLoginChallenge($user->mobile);

    Mail::assertSent(\App\Mail\TemplatedNotificationMail::class, 1);

    $log = NotificationLog::query()->where('template_code', 'auth.login_otp')->first();
    expect($log)->not->toBeNull()
        ->and($log->recipient)->toBe('reception@example.com')
        ->and($log->status)->toBe('sent');
});

function NotificationTemplateEnsure(string $code, string $channel): void
{
    \App\Domain\Notifications\Models\NotificationTemplate::firstOrCreate(
        [
            'hospital_id' => \App\Domain\Hospitals\Models\Hospital::where('code', 'DEMOHIMS')->value('id'),
            'code' => $code,
            'channel' => $channel,
        ],
        [
            'subject' => 'Test',
            'body' => 'Amount {{amount}} {{invoice_number}} {{receipt_number}}',
            'status' => 'active',
        ],
    );
}
