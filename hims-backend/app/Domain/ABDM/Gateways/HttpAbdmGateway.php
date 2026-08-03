<?php

namespace App\Domain\ABDM\Gateways;

use App\Domain\ABDM\Contracts\AbdmGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class HttpAbdmGateway implements AbdmGateway
{
    public function providerName(): string
    {
        return 'http';
    }

    public function initiateAbhaVerify(array $payload): array
    {
        $response = $this->client()->post('/api/hiecm/gateway/v3/sessions/abha/verify/init', [
            'abhaNumber' => $payload['abha_number'] ?? null,
            'mobile' => $payload['mobile'] ?? null,
        ]);

        return $this->mapInit($response->json() ?? [], 'ABHA verify initiated via gateway');
    }

    public function confirmAbhaVerify(array $payload): array
    {
        $response = $this->client()->post('/api/hiecm/gateway/v3/sessions/abha/verify/confirm', [
            'txnId' => $payload['external_txn_id'],
            'otp' => $payload['otp'],
        ]);

        return $this->mapConfirm($response->json() ?? [], 'verified', 'ABHA verified via gateway');
    }

    public function initiateAbhaCreate(array $payload): array
    {
        $response = $this->client()->post('/api/hiecm/gateway/v3/registration/aadhaar/generateOtp', [
            'aadhaar' => $payload['aadhaar_number'] ?? null,
            'mobile' => $payload['mobile'] ?? null,
        ]);

        return $this->mapInit($response->json() ?? [], 'ABHA create initiated via gateway');
    }

    public function confirmAbhaCreate(array $payload): array
    {
        $response = $this->client()->post('/api/hiecm/gateway/v3/registration/aadhaar/verifyOtp', [
            'txnId' => $payload['external_txn_id'],
            'otp' => $payload['otp'],
            'mobile' => $payload['mobile'] ?? null,
        ]);

        return $this->mapConfirm($response->json() ?? [], 'created', 'ABHA created via gateway');
    }

    public function resolveScanShare(array $payload): array
    {
        $response = $this->client()->post('/api/hiecm/gateway/v3/patients/profile/share', [
            'hipId' => config('abdm.facility_id'),
            'counterId' => $payload['counter_id'] ?? 'OPD-1',
            'token' => $payload['share_code'] ?? $payload['token'] ?? null,
        ]);

        return $this->mapConfirm($response->json() ?? [], 'resolved', 'Scan & Share resolved via gateway');
    }

    private function client(): PendingRequest
    {
        $clientId = config('abdm.client_id');
        $clientSecret = config('abdm.client_secret');

        if (! $clientId || ! $clientSecret) {
            throw new RuntimeException('ABDM HTTP credentials are not configured.');
        }

        return Http::baseUrl((string) config('abdm.base_url'))
            ->timeout((int) config('abdm.timeout_seconds', 20))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-CM-ID' => (string) config('abdm.cm_id', 'sbx'),
                'REQUEST-ID' => (string) Str::uuid(),
                'TIMESTAMP' => now()->toIso8601String(),
            ])
            ->withBasicAuth((string) $clientId, (string) $clientSecret);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{external_txn_id: string, status: string, message: string, raw?: array<string, mixed>}
     */
    private function mapInit(array $json, string $message): array
    {
        if (isset($json['error']) || (($json['status'] ?? null) === 'ERROR')) {
            throw new RuntimeException((string) ($json['error']['message'] ?? $json['message'] ?? 'ABDM gateway init failed'));
        }

        return [
            'external_txn_id' => (string) ($json['txnId'] ?? $json['external_txn_id'] ?? Str::uuid()),
            'status' => 'otp_sent',
            'message' => $message,
            'raw' => $json,
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{external_txn_id: string, status: string, profile?: array<string, mixed>, message: string, raw?: array<string, mixed>}
     */
    private function mapConfirm(array $json, string $status, string $message): array
    {
        if (isset($json['error']) || (($json['status'] ?? null) === 'ERROR')) {
            throw new RuntimeException((string) ($json['error']['message'] ?? $json['message'] ?? 'ABDM gateway confirm failed'));
        }

        $profileSource = $json['ABHAProfile'] ?? $json['profile'] ?? $json['patient'] ?? [];

        return [
            'external_txn_id' => (string) ($json['txnId'] ?? $json['external_txn_id'] ?? Str::uuid()),
            'status' => $status,
            'message' => $message,
            'profile' => [
                'abha_number' => $profileSource['ABHANumber'] ?? $profileSource['abha_number'] ?? null,
                'abha_address' => $profileSource['preferredAbhaAddress'] ?? $profileSource['abha_address'] ?? null,
                'abha_id' => $profileSource['ABHANumber'] ?? $profileSource['abha_id'] ?? null,
                'first_name' => $profileSource['firstName'] ?? $profileSource['first_name'] ?? null,
                'last_name' => $profileSource['lastName'] ?? $profileSource['last_name'] ?? null,
                'mobile' => $profileSource['mobile'] ?? null,
                'gender' => $profileSource['gender'] ?? 'unknown',
                'date_of_birth' => $profileSource['dob'] ?? $profileSource['date_of_birth'] ?? null,
            ],
            'raw' => $json,
        ];
    }
}
