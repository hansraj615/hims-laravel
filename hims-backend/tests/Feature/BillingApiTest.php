<?php

use App\Domain\Billing\Models\CashierDaybook;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Service;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
    Sanctum::actingAs(User::where('email', 'billing@example.com')->firstOrFail());
});

it('creates an invoice with server calculated GST line items', function () {
    $patient = createTestPatient(['mobile' => '9900004001']);

    $serviceId = $this->postJson('/api/v1/billing/services', [
        'name' => 'Blood Test Panel',
        'code' => 'LABPANEL',
        'service_type' => 'diagnostic',
        'category' => 'pathology',
        'base_rate' => 1000,
        'cgst_rate' => 6,
        'sgst_rate' => 6,
        'is_tax_exempt' => false,
    ])->assertCreated()->json('data.id');

    $response = $this->postJson('/api/v1/billing/invoices', [
        'patient_id' => $patient->id,
        'payer_type' => 'self',
        'items' => [
            ['service_id' => $serviceId, 'quantity' => 1],
            ['description' => 'Courier charge', 'quantity' => 1, 'unit_rate' => 200, 'is_tax_exempt' => false, 'igst_rate' => 12],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.taxable_total', '1200.00')
        ->assertJsonPath('data.cgst_total', '60.00')
        ->assertJsonPath('data.sgst_total', '60.00')
        ->assertJsonPath('data.igst_total', '24.00')
        ->assertJsonPath('data.grand_total', '1344.00')
        ->assertJsonPath('data.balance_total', '1344.00');

    $invoice = Invoice::findOrFail($response->json('data.id'));
    expect($invoice->items()->count())->toBe(2);
});

it('finalizes a draft invoice and marks it billed', function () {
    $invoiceId = createDraftInvoice()->id;

    $response = $this->postJson("/api/v1/billing/invoices/{$invoiceId}/finalize");

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'issued');

    expect(Invoice::findOrFail($invoiceId)->billed_at)->not->toBeNull();

    // A finalized invoice can no longer be edited as a draft.
    $this->putJson("/api/v1/billing/invoices/{$invoiceId}", [
        'patient_id' => Invoice::findOrFail($invoiceId)->patient_id,
        'items' => [['description' => 'Should fail', 'quantity' => 1, 'unit_rate' => 100]],
    ])->assertStatus(422);
});

it('posts a payment atomically, updates invoice balance and opens the cashier daybook', function () {
    $invoice = createDraftInvoice(500);
    $this->postJson("/api/v1/billing/invoices/{$invoice->id}/finalize")->assertOk();

    $first = $this->postJson("/api/v1/billing/invoices/{$invoice->id}/payments", [
        'payment_mode' => 'cash',
        'amount' => 300,
    ]);

    $first
        ->assertCreated()
        ->assertJsonPath('data.amount', '300.00');

    $invoice->refresh();
    expect((float) $invoice->paid_total)->toBe(300.0)
        ->and((float) $invoice->balance_total)->toBe(200.0)
        ->and($invoice->status)->toBe('partially_paid');

    $billingUser = User::where('email', 'billing@example.com')->firstOrFail();

    $daybook = CashierDaybook::where('cashier_user_id', $billingUser->id)
        ->whereDate('business_date', today())
        ->first();

    expect($daybook)->not->toBeNull()
        ->and((float) $daybook->cash_collected)->toBe(300.0);

    $second = $this->postJson("/api/v1/billing/invoices/{$invoice->id}/payments", [
        'payment_mode' => 'upi',
        'amount' => 200,
        'reference_number' => 'UPI123456',
    ]);

    $second->assertCreated();

    $invoice->refresh();
    expect((float) $invoice->paid_total)->toBe(500.0)
        ->and((float) $invoice->balance_total)->toBe(0.0)
        ->and($invoice->status)->toBe('paid');

    expect($invoice->payments()->count())->toBe(2);
});

it('rejects a payment that would overpay the invoice balance', function () {
    $invoice = createDraftInvoice(500);
    $this->postJson("/api/v1/billing/invoices/{$invoice->id}/finalize")->assertOk();

    $this->postJson("/api/v1/billing/invoices/{$invoice->id}/payments", [
        'payment_mode' => 'cash',
        'amount' => 600,
    ])->assertStatus(422);

    expect($invoice->refresh()->paid_total)->toBe('0.00');
});

it('voids only unpaid invoices and refuses to void a paid invoice', function () {
    $unpaid = createDraftInvoice(400);
    $this->postJson("/api/v1/billing/invoices/{$unpaid->id}/finalize")->assertOk();

    $this->postJson("/api/v1/billing/invoices/{$unpaid->id}/void")
        ->assertOk()
        ->assertJsonPath('data.status', 'voided');

    $paid = createDraftInvoice(250);
    $this->postJson("/api/v1/billing/invoices/{$paid->id}/finalize")->assertOk();
    $this->postJson("/api/v1/billing/invoices/{$paid->id}/payments", [
        'payment_mode' => 'cash',
        'amount' => 250,
    ])->assertCreated();

    $this->postJson("/api/v1/billing/invoices/{$paid->id}/void")
        ->assertStatus(422);
});

it('denies billing access for a user lacking billing.manage', function () {
    Sanctum::actingAs(User::where('email', 'doctor@example.com')->firstOrFail());

    $this->getJson('/api/v1/billing/invoices')->assertForbidden();
});

function createDraftInvoice(float $rate = 500): Invoice
{
    $patient = createTestPatient(['mobile' => (string) random_int(9900005000, 9900005999)]);

    $service = Service::firstOrCreate(
        ['hospital_id' => $patient->hospital_id, 'code' => 'GENCONSULT'],
        [
            'name' => 'General Consultation',
            'service_type' => 'consultation',
            'category' => 'consultant_fee',
            'base_rate' => $rate,
            'is_tax_exempt' => true,
            'status' => 'active',
        ],
    );

    $invoiceId = test()->postJson('/api/v1/billing/invoices', [
        'patient_id' => $patient->id,
        'items' => [
            ['service_id' => $service->id, 'quantity' => 1, 'unit_rate' => $rate],
        ],
    ])->assertCreated()->json('data.id');

    return Invoice::findOrFail($invoiceId);
}
