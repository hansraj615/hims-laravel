<?php

declare(strict_types=1);

$base = 'http://localhost:8000';
$cookie = tempnam(sys_get_temp_dir(), 'hims');

function request(string $method, string $url, string $cookie, ?string $xsrf = null, ?array $body = null): array
{
    $ch = curl_init($url);
    $headers = [
        'Origin: http://localhost:5173',
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Requested-With: XMLHttpRequest',
    ];

    if ($xsrf) {
        $headers[] = 'X-XSRF-TOKEN: '.$xsrf;
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $responseBody = substr((string) $raw, $headerSize);

    return [$code, json_decode($responseBody, true), $responseBody];
}

function xsrfFromJar(string $cookie): string
{
    $jar = file_get_contents($cookie) ?: '';
    if (! preg_match('/\tXSRF-TOKEN\t(\S+)/', $jar, $matches)) {
        return '';
    }

    return urldecode($matches[1]);
}

function step(string $label, int $code, ?array $json, string $raw): void
{
    $summary = $json['message'] ?? $json['data'] ?? $raw;
    if (is_array($summary)) {
        $summary = json_encode($summary);
    }
    echo $label.' ['.$code.'] '.$summary.PHP_EOL;
}

request('GET', $base.'/sanctum/csrf-cookie', $cookie);
$xsrf = xsrfFromJar($cookie);

[$code, $json, $raw] = request('POST', $base.'/api/v1/auth/login', $cookie, $xsrf, [
    'email' => 'reception@example.com',
    'password' => 'password',
]);
step('LOGIN reception', $code, $json, $raw);
$xsrf = xsrfFromJar($cookie);

[$code, $json, $raw] = request('POST', $base.'/api/v1/patients', $cookie, $xsrf, [
    'patient_category' => 'general',
    'registration_source' => 'walk_in',
    'first_name' => 'Ravi',
    'last_name' => 'Patel',
    'gender' => 'male',
    'age_years' => 28,
    'mobile' => '9876501234',
    'nationality' => 'Indian',
    'preferred_language' => 'English',
    'country' => 'India',
    'consent_sms' => true,
    'consent_email' => false,
    'consent_whatsapp' => false,
    'status' => 'active',
]);
step('CREATE patient', $code, $json, $raw);
$patientId = $json['data']['id'] ?? null;
$uhid = $json['data']['uhid'] ?? null;
$xsrf = xsrfFromJar($cookie);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$doctorId = App\Models\User::where('email', 'doctor@example.com')->value('id');
$departmentId = App\Domain\Hospitals\Models\Department::where('code', 'GENMED')->value('id');

[$code, $json, $raw] = request('POST', $base.'/api/v1/appointments', $cookie, $xsrf, [
    'patient_id' => $patientId,
    'department_id' => $departmentId,
    'doctor_user_id' => $doctorId,
    'appointment_date' => now()->toDateString(),
    'slot_start' => '10:00',
    'slot_end' => '10:15',
    'visit_type' => 'first_visit',
    'source' => 'walk_in',
    'priority' => 'normal',
    'fee_amount' => 500,
    'reason' => 'Fever and cough',
]);
step('BOOK appointment', $code, $json, $raw);
$appointmentId = $json['data']['id'] ?? null;
$xsrf = xsrfFromJar($cookie);

[$code, $json, $raw] = request('POST', $base.'/api/v1/appointments/'.$appointmentId.'/check-in', $cookie, $xsrf, []);
step('CHECK-IN', $code, $json, $raw);
$queueId = $json['data']['queue_entry']['id'] ?? null;
if ($queueId === null) {
    // check-in may return appointment with nested queue differently
    $queueId = App\Domain\OPD\Models\OpdQueue::where('appointment_id', $appointmentId)->value('id');
}
echo 'QUEUE_ID '.$queueId.PHP_EOL;
$xsrf = xsrfFromJar($cookie);
request('POST', $base.'/api/v1/auth/logout', $cookie, $xsrf, []);

// Doctor flow
request('GET', $base.'/sanctum/csrf-cookie', $cookie);
$xsrf = xsrfFromJar($cookie);
[$code, $json, $raw] = request('POST', $base.'/api/v1/auth/login', $cookie, $xsrf, [
    'email' => 'doctor@example.com',
    'password' => 'password',
]);
step('LOGIN doctor', $code, $json, $raw);
$xsrf = xsrfFromJar($cookie);

[$code, $json, $raw] = request('POST', $base.'/api/v1/opd/consultations', $cookie, $xsrf, [
    'opd_queue_id' => $queueId,
]);
step('START consultation', $code, $json, $raw);
$encounterId = $json['data']['id'] ?? null;
$xsrf = xsrfFromJar($cookie);

[$code, $json, $raw] = request('PUT', $base.'/api/v1/opd/consultations/'.$encounterId, $cookie, $xsrf, [
    'vitals' => ['temperature_c' => 38.4, 'pulse_bpm' => 92],
    'chief_complaints' => ['Fever', 'Cough'],
    'diagnoses' => [['display' => 'Viral fever', 'system' => 'local']],
    'care_plan' => ['notes' => 'Rest and hydration'],
    'prescription_items' => [[
        'medicine_name' => 'Paracetamol',
        'strength' => '500mg',
        'frequency' => 'TDS',
        'duration' => '3 days',
        'quantity' => 9,
    ]],
]);
step('UPDATE consultation', $code, $json, $raw);
$xsrf = xsrfFromJar($cookie);

[$code, $json, $raw] = request('POST', $base.'/api/v1/opd/consultations/'.$encounterId.'/complete', $cookie, $xsrf, []);
step('COMPLETE consultation', $code, $json, $raw);
$invoiceId = App\Domain\Billing\Models\Invoice::where('encounter_id', $encounterId)->value('id');
echo 'DRAFT_INVOICE '.$invoiceId.PHP_EOL;
$xsrf = xsrfFromJar($cookie);
request('POST', $base.'/api/v1/auth/logout', $cookie, $xsrf, []);

// Billing flow
request('GET', $base.'/sanctum/csrf-cookie', $cookie);
$xsrf = xsrfFromJar($cookie);
[$code, $json, $raw] = request('POST', $base.'/api/v1/auth/login', $cookie, $xsrf, [
    'email' => 'billing@example.com',
    'password' => 'password',
]);
step('LOGIN billing', $code, $json, $raw);
$xsrf = xsrfFromJar($cookie);

if (! $invoiceId) {
    $serviceId = App\Domain\Billing\Models\Service::where('code', 'OPDCONSULT')->value('id');
    [$code, $json, $raw] = request('POST', $base.'/api/v1/billing/invoices', $cookie, $xsrf, [
        'patient_id' => $patientId,
        'invoice_type' => 'opd',
        'payer_type' => 'self',
        'items' => [[
            'service_id' => $serviceId,
            'quantity' => 1,
        ]],
    ]);
    step('CREATE invoice', $code, $json, $raw);
    $invoiceId = $json['data']['id'] ?? null;
    $xsrf = xsrfFromJar($cookie);
}

[$code, $json, $raw] = request('POST', $base.'/api/v1/billing/invoices/'.$invoiceId.'/finalize', $cookie, $xsrf, []);
step('FINALIZE invoice', $code, $json, $raw);
$xsrf = xsrfFromJar($cookie);

$balance = (float) ($json['data']['balance_total'] ?? $json['data']['grand_total'] ?? 0);
[$code, $json, $raw] = request('POST', $base.'/api/v1/billing/invoices/'.$invoiceId.'/payments', $cookie, $xsrf, [
    'payment_mode' => 'cash',
    'amount' => $balance > 0 ? $balance : 500,
]);
step('POST payment', $code, $json, $raw);
$xsrf = xsrfFromJar($cookie);

[$code, $json, $raw] = request('GET', $base.'/api/v1/billing/invoices/'.$invoiceId.'/receipt', $cookie, $xsrf);
step('RECEIPT', $code, $json, $raw);

echo PHP_EOL.'SUMMARY uhid='.$uhid.' patient='.$patientId.' appointment='.$appointmentId.' queue='.$queueId.' encounter='.$encounterId.' invoice='.$invoiceId.PHP_EOL;

@unlink($cookie);
