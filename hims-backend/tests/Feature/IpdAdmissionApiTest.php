<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\IPD\Models\Admission;
use App\Domain\IPD\Models\Bed;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientDocument;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
});

function clearAllClearances(int $admissionId): void
{
    foreach (['nursing', 'diagnostics', 'billing', 'ward'] as $type) {
        test()->postJson("/api/v1/ipd/admissions/{$admissionId}/clearances", [
            'clearance_type' => $type,
            'status' => 'cleared',
        ])->assertOk();
    }
}

it('admits a patient to an available bed and occupies it', function () {
    $patient = createIpdTestPatient(['mobile' => '9900009101']);
    $bed = Bed::query()->where('bed_number', 'G-01')->firstOrFail();

    $response = $this->postJson('/api/v1/ipd/admissions', [
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'provisional_diagnosis' => 'Acute fever',
        'attendant_name' => 'Ravi',
        'attendant_mobile' => '9900009111',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', 'admitted')
        ->assertJsonPath('data.bed_id', $bed->id);

    expect(Admission::findOrFail($response->json('data.id'))->admission_number)->toContain('-IPD-')
        ->and(Bed::findOrFail($bed->id)->status)->toBe('occupied')
        ->and(Bed::findOrFail($bed->id)->current_admission_id)->toBe($response->json('data.id'));
});

it('transfers an admitted patient and frees the previous bed', function () {
    $patient = createIpdTestPatient(['mobile' => '9900009102']);
    $from = Bed::query()->where('bed_number', 'G-01')->firstOrFail();
    $to = Bed::query()->where('bed_number', 'ICU-01')->firstOrFail();

    $admissionId = $this->postJson('/api/v1/ipd/admissions', [
        'patient_id' => $patient->id,
        'bed_id' => $from->id,
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/ipd/admissions/{$admissionId}/transfer", [
        'bed_id' => $to->id,
        'reason' => 'Needs monitoring',
    ])
        ->assertOk()
        ->assertJsonPath('data.bed_id', $to->id);

    expect(Bed::findOrFail($from->id)->status)->toBe('available')
        ->and(Bed::findOrFail($from->id)->current_admission_id)->toBeNull()
        ->and(Bed::findOrFail($to->id)->status)->toBe('occupied');
});

it('documents DOPR exit with attached package and draft IPD invoice', function () {
    $patient = createIpdTestPatient(['mobile' => '9900009103']);
    $bed = Bed::query()->where('bed_number', 'G-02')->firstOrFail();

    $admissionId = $this->postJson('/api/v1/ipd/admissions', [
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
    ])->assertCreated()->json('data.id');

    PatientDocument::create([
        'hospital_id' => $patient->hospital_id,
        'patient_id' => $patient->id,
        'document_type' => 'pathology_report',
        'title' => 'CBC during stay',
        'uploaded_by' => User::where('email', 'reception@example.com')->value('id'),
    ]);

    $this->postJson("/api/v1/ipd/admissions/{$admissionId}/discharge", [
        'outcome' => 'dopr',
        'discharge_summary' => 'Blocked without clearances',
    ])->assertStatus(422);

    clearAllClearances($admissionId);

    $exit = $this->postJson("/api/v1/ipd/admissions/{$admissionId}/discharge", [
        'outcome' => 'dopr',
        'discharge_summary' => 'Patient requested discharge against advice.',
        'create_invoice' => true,
    ])->assertOk();

    $exit
        ->assertJsonPath('data.admission.status', 'dopr')
        ->assertJsonPath('data.admission.discharge_outcome', 'dopr')
        ->assertJsonPath('data.invoice.invoice_type', 'ipd');

    $admission = Admission::findOrFail($admissionId);
    expect($admission->discharge_package['counts']['documents'])->toBeGreaterThanOrEqual(1)
        ->and(PatientDocument::query()->where('document_type', 'dopr_summary')->where('patient_id', $patient->id)->exists())->toBeTrue()
        ->and(Bed::findOrFail($bed->id)->status)->toBe('available')
        ->and(Invoice::findOrFail($exit->json('data.invoice.id'))->status)->toBe('draft');
});

it('supports death outcome and rejects double active admission', function () {
    $patient = createIpdTestPatient(['mobile' => '9900009104']);
    $bedA = Bed::query()->where('bed_number', 'G-03')->firstOrFail();
    $bedB = Bed::query()->where('bed_number', 'G-04')->firstOrFail();

    $admissionId = $this->postJson('/api/v1/ipd/admissions', [
        'patient_id' => $patient->id,
        'bed_id' => $bedA->id,
    ])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/ipd/admissions', [
        'patient_id' => $patient->id,
        'bed_id' => $bedB->id,
    ])->assertStatus(422);

    clearAllClearances($admissionId);

    $this->postJson("/api/v1/ipd/admissions/{$admissionId}/discharge", [
        'outcome' => 'death',
        'discharge_summary' => 'Death summary with all attached reports.',
        'death_at' => now()->toISOString(),
    ])
        ->assertOk()
        ->assertJsonPath('data.admission.status', 'deceased')
        ->assertJsonPath('data.admission.discharge_outcome', 'death');

    expect(PatientDocument::query()->where('document_type', 'death_summary')->exists())->toBeTrue();
});

it('records nursing notes and posts daily bed-day charges', function () {
    $patient = createIpdTestPatient(['mobile' => '9900009105']);
    $bed = Bed::query()->where('bed_number', 'G-05')->firstOrFail();

    Sanctum::actingAs(User::where('email', 'nurse@example.com')->firstOrFail());

    $admissionId = $this->postJson('/api/v1/ipd/admissions', [
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/ipd/admissions/{$admissionId}/nursing-notes", [
        'notes' => 'Comfortable, vitals stable',
        'vitals' => ['pulse_bpm' => 78, 'bp_systolic' => 120, 'bp_diastolic' => 80],
    ])
        ->assertCreated()
        ->assertJsonPath('data.notes', 'Comfortable, vitals stable');

    $this->getJson("/api/v1/ipd/admissions/{$admissionId}/charges")
        ->assertOk()
        ->assertJsonFragment(['source' => 'auto_bed_day', 'status' => 'open']);

    $this->postJson("/api/v1/ipd/admissions/{$admissionId}/charges/daily")
        ->assertOk()
        ->assertJsonPath('data.created_count', 0);
});

it('loads the bed board for nurse with ipd.manage', function () {
    Sanctum::actingAs(User::where('email', 'nurse@example.com')->firstOrFail());

    $this->getJson('/api/v1/ipd/beds/board')
        ->assertOk()
        ->assertJsonFragment(['bed_number' => 'G-01']);
});

function createIpdTestPatient(array $overrides = []): Patient
{
    $hospital = Hospital::where('code', 'DEMOHIMS')->firstOrFail();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    return Patient::create([
        'hospital_id' => $hospital->id,
        'branch_id' => $branch->id,
        'uhid' => 'DEMOHIMS-'.str_pad((string) random_int(300000, 399999), 6, '0', STR_PAD_LEFT),
        'first_name' => 'Ipd',
        'last_name' => 'Patient',
        'gender' => 'unknown',
        'mobile' => '9900009199',
        'status' => 'active',
        ...$overrides,
    ]);
}
