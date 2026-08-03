<?php

namespace App\Domain\ABDM\Gateways;

use App\Domain\ABDM\Contracts\AbdmGateway;
use Illuminate\Support\Str;
use RuntimeException;

class SimulatedAbdmGateway implements AbdmGateway
{
    public function providerName(): string
    {
        return 'simulated';
    }

    public function initiateAbhaVerify(array $payload): array
    {
        $txn = 'SIM-VFY-'.Str::upper(Str::random(10));

        return [
            'external_txn_id' => $txn,
            'status' => 'otp_sent',
            'message' => 'Simulated ABHA verify OTP sent. Use ABDM_DEMO_OTP to confirm.',
            'raw' => [
                'mode' => 'simulated',
                'identifier' => $payload['abha_number'] ?? $payload['mobile'] ?? null,
            ],
        ];
    }

    public function confirmAbhaVerify(array $payload): array
    {
        $this->assertOtp($payload['otp'] ?? null);

        $abhaNumber = preg_replace('/\D+/', '', (string) ($payload['abha_number'] ?? '12-3456-7890-1234')) ?: '12345678901234';
        $mobile = $payload['mobile'] ?? '9999999999';

        return [
            'external_txn_id' => (string) $payload['external_txn_id'],
            'status' => 'verified',
            'message' => 'Simulated ABHA verified.',
            'profile' => $this->profile($abhaNumber, $mobile, $payload['first_name'] ?? 'Verified', $payload['last_name'] ?? 'Patient'),
            'raw' => ['mode' => 'simulated'],
        ];
    }

    public function initiateAbhaCreate(array $payload): array
    {
        $txn = 'SIM-CRT-'.Str::upper(Str::random(10));

        return [
            'external_txn_id' => $txn,
            'status' => 'otp_sent',
            'message' => 'Simulated ABHA create OTP sent. Use ABDM_DEMO_OTP to confirm.',
            'raw' => [
                'mode' => 'simulated',
                'aadhaar_last4' => isset($payload['aadhaar_number'])
                    ? substr(preg_replace('/\D+/', '', (string) $payload['aadhaar_number']), -4)
                    : null,
            ],
        ];
    }

    public function confirmAbhaCreate(array $payload): array
    {
        $this->assertOtp($payload['otp'] ?? null);

        $seed = preg_replace('/\D+/', '', (string) ($payload['aadhaar_number'] ?? $payload['mobile'] ?? '987654321098')) ?: '987654321098';
        $abhaNumber = substr(str_pad($seed, 14, '0'), 0, 14);
        $mobile = $payload['mobile'] ?? '9999999999';

        return [
            'external_txn_id' => (string) $payload['external_txn_id'],
            'status' => 'created',
            'message' => 'Simulated ABHA created.',
            'profile' => $this->profile($abhaNumber, $mobile, $payload['first_name'] ?? 'New', $payload['last_name'] ?? 'Abha'),
            'raw' => ['mode' => 'simulated'],
        ];
    }

    public function resolveScanShare(array $payload): array
    {
        $token = (string) ($payload['share_code'] ?? $payload['token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Scan & Share token is required.');
        }

        $abhaNumber = substr(preg_replace('/\D+/', '', hash('crc32b', $token)).'12345678901234', 0, 14);

        return [
            'external_txn_id' => 'SIM-SCN-'.Str::upper(Str::random(10)),
            'status' => 'resolved',
            'message' => 'Simulated Scan & Share profile resolved.',
            'profile' => $this->profile($abhaNumber, '9888877776', 'Scan', 'Share'),
            'raw' => [
                'mode' => 'simulated',
                'share_code' => $token,
            ],
        ];
    }

    private function assertOtp(?string $otp): void
    {
        $expected = (string) config('abdm.demo_otp', '123456');
        if (! hash_equals($expected, (string) $otp)) {
            throw new RuntimeException('Invalid ABDM OTP.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(string $abhaNumber, string $mobile, string $firstName, string $lastName): array
    {
        $formatted = trim(chunk_split($abhaNumber, 2, '-'), '-');

        return [
            'abha_number' => $formatted,
            'abha_address' => Str::lower($firstName).$abhaNumber.'@sbx',
            'abha_id' => $formatted,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'mobile' => $mobile,
            'gender' => 'unknown',
            'date_of_birth' => null,
        ];
    }
}
