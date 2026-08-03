<?php

use App\Domain\ABDM\Models\AbdmTransaction;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Patients\Models\Patient;
use App\Models\User;
use Database\Seeders\HimsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(HimsDemoSeeder::class);
    Config::set('abdm.enabled', true);
    Config::set('abdm.mode', 'simulated');
    Config::set('abdm.demo_otp', '123456');
    Sanctum::actingAs(User::where('email', 'reception@example.com')->firstOrFail());
});

it('reports simulated provider when credentials are absent', function () {
    $this->getJson('/api/v1/abdm/status')
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.provider', 'simulated');
});

it('verifies an existing ABHA and links it to a patient', function () {
    $patient = createAbdmTestPatient(['mobile' => '9900009501']);

    $init = $this->postJson('/api/v1/abdm/abha/verify/init', [
        'abha_number' => '12-3456-7890-1234',
        'patient_id' => $patient->id,
    ])->assertOk();

    $txn = $init->json('data.external_txn_id');

    $confirm = $this->postJson('/api/v1/abdm/abha/verify/confirm', [
        'external_txn_id' => $txn,
        'otp' => '123456',
        'abha_number' => '12-3456-7890-1234',
        'patient_id' => $patient->id,
        'link_patient' => true,
    ])->assertOk();

    $confirm
        ->assertJsonPath('data.status', 'verified')
        ->assertJsonPath('data.patient.abha_verification_status', 'verified');

    expect(Patient::findOrFail($patient->id)->abha_number)->not->toBeNull()
        ->and(AbdmTransaction::query()->where('operation', 'abha.verify.confirm')->where('status', 'verified')->exists())->toBeTrue();
});

it('creates an ABHA via simulated OTP flow', function () {
    $init = $this->postJson('/api/v1/abdm/abha/create/init', [
        'aadhaar_number' => '999988887777',
        'mobile' => '9900009502',
        'first_name' => 'Asha',
        'last_name' => 'Devi',
    ])->assertOk();

    $this->postJson('/api/v1/abdm/abha/create/confirm', [
        'external_txn_id' => $init->json('data.external_txn_id'),
        'otp' => '123456',
        'aadhaar_number' => '999988887777',
        'mobile' => '9900009502',
        'first_name' => 'Asha',
        'last_name' => 'Devi',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'created')
        ->assertJsonPath('data.profile.first_name', 'Asha');
});

it('resolves Scan & Share and registers a patient', function () {
    $response = $this->postJson('/api/v1/abdm/scan-share', [
        'share_code' => 'OPD-TOKEN-DEMO-001',
        'register_patient' => true,
    ])->assertCreated();

    $response
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.patient.abha_verification_status', 'verified')
        ->assertJsonPath('data.patient.registration_source', 'online');

    expect($response->json('data.patient.uhid'))->not->toBeNull();
});

it('rejects ABDM calls when the feature flag is off', function () {
    Config::set('abdm.enabled', false);

    $this->postJson('/api/v1/abdm/abha/verify/init', [
        'mobile' => '9900009503',
    ])->assertStatus(503);
});

function createAbdmTestPatient(array $overrides = []): Patient
{
    $hospital = Hospital::where('code', 'DEMOHIMS')->firstOrFail();
    $branch = Branch::where('code', 'MAIN')->firstOrFail();

    return Patient::create([
        'hospital_id' => $hospital->id,
        'branch_id' => $branch->id,
        'uhid' => 'DEMOHIMS-'.str_pad((string) random_int(400000, 499999), 6, '0', STR_PAD_LEFT),
        'first_name' => 'Abdm',
        'last_name' => 'Patient',
        'gender' => 'unknown',
        'mobile' => '9900009599',
        'status' => 'active',
        ...$overrides,
    ]);
}
