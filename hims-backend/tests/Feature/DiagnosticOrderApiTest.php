<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Service;
use App\Domain\Diagnostics\Models\DiagnosticOrder;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientDocument;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
});

it('runs pathology order through collect, result and bill', function () {
    $patient = createDiagnosticTestPatient(['mobile' => '9900008201']);
    $cbc = Service::query()->where('code', 'CBC')->firstOrFail();

    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());

    $orderId = $this->postJson('/api/v1/diagnostics/orders', [
        'patient_id' => $patient->id,
        'category' => 'pathology',
        'priority' => 'urgent',
        'clinical_notes' => 'Fever workup',
        'items' => [['service_id' => $cbc->id, 'quantity' => 1]],
    ])
        ->assertCreated()
        ->assertJsonPath('data.category', 'pathology')
        ->assertJsonPath('data.status', 'ordered')
        ->json('data.id');

    expect(DiagnosticOrder::findOrFail($orderId)->order_number)->toContain('-DX-');

    Sanctum::actingAs(User::where('email', 'lab@example.com')->firstOrFail());

    $this->postJson("/api/v1/diagnostics/orders/{$orderId}/collect")
        ->assertOk()
        ->assertJsonPath('data.status', 'sample_collected');

    $this->postJson("/api/v1/diagnostics/orders/{$orderId}/result", [
        'result_summary' => 'CBC within normal limits',
        'result_payload' => ['hb' => 13.2],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'result_ready')
        ->assertJsonPath('data.result_summary', 'CBC within normal limits');

    expect(PatientDocument::query()->where('patient_id', $patient->id)->where('document_type', 'pathology_report')->exists())->toBeTrue();

    Sanctum::actingAs(User::where('email', 'billing@example.com')->firstOrFail());

    $bill = $this->postJson("/api/v1/diagnostics/orders/{$orderId}/bill")
        ->assertCreated()
        ->assertJsonPath('data.order.status', 'billed');

    $invoiceId = $bill->json('data.invoice.id');
    expect(Invoice::findOrFail($invoiceId)->invoice_type)->toBe('pathology')
        ->and((float) Invoice::findOrFail($invoiceId)->grand_total)->toBe(350.0);

    $this->postJson("/api/v1/diagnostics/orders/{$orderId}/bill")->assertStatus(422);
});

it('allows radiology and procedure orders without collection step', function () {
    $patient = createDiagnosticTestPatient(['mobile' => '9900008202']);
    $xray = Service::query()->where('code', 'XRAYCHEST')->firstOrFail();
    $dressing = Service::query()->where('code', 'DRESSING')->firstOrFail();

    Sanctum::actingAs(User::where('email', 'doctor@example.com')->firstOrFail());

    $radiologyId = $this->postJson('/api/v1/diagnostics/orders', [
        'patient_id' => $patient->id,
        'category' => 'radiology',
        'items' => [['service_id' => $xray->id]],
    ])->assertCreated()->json('data.id');

    $procedureId = $this->postJson('/api/v1/diagnostics/orders', [
        'patient_id' => $patient->id,
        'category' => 'procedure',
        'items' => [['service_id' => $dressing->id]],
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs(User::where('email', 'lab@example.com')->firstOrFail());

    $this->postJson("/api/v1/diagnostics/orders/{$radiologyId}/result", [
        'result_summary' => 'No acute finding',
    ])->assertOk()->assertJsonPath('data.status', 'result_ready');

    $this->postJson("/api/v1/diagnostics/orders/{$procedureId}/result", [
        'result_summary' => 'Dressing completed',
    ])->assertOk()->assertJsonPath('data.status', 'result_ready');
});

it('rejects service category mismatch and unauthorized result entry', function () {
    $patient = createDiagnosticTestPatient(['mobile' => '9900008203']);
    $cbc = Service::query()->where('code', 'CBC')->firstOrFail();

    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());

    $this->postJson('/api/v1/diagnostics/orders', [
        'patient_id' => $patient->id,
        'category' => 'radiology',
        'items' => [['service_id' => $cbc->id]],
    ])->assertStatus(422);

    $orderId = $this->postJson('/api/v1/diagnostics/orders', [
        'patient_id' => $patient->id,
        'category' => 'pathology',
        'items' => [['service_id' => $cbc->id]],
    ])->assertCreated()->json('data.id');

    // Reception cannot enter results.
    $this->postJson("/api/v1/diagnostics/orders/{$orderId}/result", [
        'result_summary' => 'Should fail',
    ])->assertForbidden();
});

it('exposes diagnostic catalog for order creators', function () {
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());

    $this->getJson('/api/v1/diagnostics/orders/catalog?category=pathology')
        ->assertOk()
        ->assertJsonFragment(['code' => 'CBC', 'category' => 'pathology']);
});

function createDiagnosticTestPatient(array $overrides = []): Patient
{
    $hospital = Hospital::where('code', 'DEMOHIMS')->firstOrFail();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    return Patient::create([
        'hospital_id' => $hospital->id,
        'branch_id' => $branch->id,
        'uhid' => 'DEMOHIMS-'.str_pad((string) random_int(200000, 299999), 6, '0', STR_PAD_LEFT),
        'first_name' => 'Dx',
        'last_name' => 'Patient',
        'gender' => 'unknown',
        'mobile' => '9900008299',
        'status' => 'active',
        ...$overrides,
    ]);
}
