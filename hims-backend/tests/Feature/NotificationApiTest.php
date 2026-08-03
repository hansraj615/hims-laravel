<?php

use App\Domain\Notifications\Models\NotificationLog;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
});

it('dispatches an in-app notification when a patient is registered', function () {
    $reception = User::where('email', 'reception@example.com')->firstOrFail();
    Sanctum::actingAs($reception);

    $this->postJson('/api/v1/patients', patientPayload([
        'first_name' => 'Notify',
        'last_name' => 'Me',
        'mobile' => '9900006001',
    ]))->assertCreated();

    $log = NotificationLog::where('template_code', 'patient.registered')->first();

    expect($log)->not->toBeNull()
        ->and($log->channel)->toBe('in_app')
        ->and($log->status)->toBe('sent')
        ->and($log->user_id)->toBe($reception->id);
});

it('dispatches an in-app notification on payment and exposes it via the notifications endpoint', function () {
    $billingUser = User::where('email', 'billing@example.com')->firstOrFail();
    Sanctum::actingAs($billingUser);

    $invoice = createDraftInvoice(500);
    $this->postJson("/api/v1/billing/invoices/{$invoice->id}/finalize")->assertOk();

    $this->postJson("/api/v1/billing/invoices/{$invoice->id}/payments", [
        'payment_mode' => 'cash',
        'amount' => 500,
    ])->assertCreated();

    $log = NotificationLog::where('template_code', 'payment.received')->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('sent')
        ->and($log->user_id)->toBe($billingUser->id);

    $response = $this->getJson('/api/v1/notifications');

    $response
        ->assertOk()
        ->assertJsonFragment(['template_code' => 'payment.received']);
});
