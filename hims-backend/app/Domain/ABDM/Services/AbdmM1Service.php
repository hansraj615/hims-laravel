<?php

namespace App\Domain\ABDM\Services;

use App\Domain\ABDM\Contracts\AbdmGateway;
use App\Domain\ABDM\Gateways\HttpAbdmGateway;
use App\Domain\ABDM\Gateways\SimulatedAbdmGateway;
use App\Domain\ABDM\Models\AbdmTransaction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Patients\Models\Patient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AbdmM1Service
{
    public function __construct(
        private readonly SimulatedAbdmGateway $simulated,
        private readonly HttpAbdmGateway $http,
    ) {
    }

    public function assertEnabled(): void
    {
        abort_unless((bool) config('abdm.enabled'), 503, 'ABDM M1 gateway is disabled. Set ABDM_ENABLED=true.');
    }

    public function gateway(): AbdmGateway
    {
        $mode = (string) config('abdm.mode', 'auto');

        if ($mode === 'simulated') {
            return $this->simulated;
        }

        if ($mode === 'http') {
            return $this->http;
        }

        $hasCredentials = filled(config('abdm.client_id')) && filled(config('abdm.client_secret'));

        return $hasCredentials ? $this->http : $this->simulated;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{transaction: AbdmTransaction, result: array<string, mixed>}
     */
    public function initiateVerify(Request $request, TenantContext $context, array $payload, AuditLogger $auditLogger): array
    {
        return $this->run(
            request: $request,
            context: $context,
            operation: 'abha.verify.init',
            payload: $payload,
            callback: fn (AbdmGateway $gateway) => $gateway->initiateAbhaVerify($payload),
            auditLogger: $auditLogger,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{transaction: AbdmTransaction, result: array<string, mixed>}
     */
    public function confirmVerify(Request $request, TenantContext $context, array $payload, AuditLogger $auditLogger): array
    {
        return $this->run(
            request: $request,
            context: $context,
            operation: 'abha.verify.confirm',
            payload: $payload,
            callback: fn (AbdmGateway $gateway) => $gateway->confirmAbhaVerify($payload),
            auditLogger: $auditLogger,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{transaction: AbdmTransaction, result: array<string, mixed>}
     */
    public function initiateCreate(Request $request, TenantContext $context, array $payload, AuditLogger $auditLogger): array
    {
        return $this->run(
            request: $request,
            context: $context,
            operation: 'abha.create.init',
            payload: $this->redactCreatePayload($payload),
            callback: fn (AbdmGateway $gateway) => $gateway->initiateAbhaCreate($payload),
            auditLogger: $auditLogger,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{transaction: AbdmTransaction, result: array<string, mixed>}
     */
    public function confirmCreate(Request $request, TenantContext $context, array $payload, AuditLogger $auditLogger): array
    {
        return $this->run(
            request: $request,
            context: $context,
            operation: 'abha.create.confirm',
            payload: $this->redactCreatePayload($payload),
            callback: fn (AbdmGateway $gateway) => $gateway->confirmAbhaCreate($payload),
            auditLogger: $auditLogger,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{transaction: AbdmTransaction, result: array<string, mixed>}
     */
    public function scanShare(Request $request, TenantContext $context, array $payload, AuditLogger $auditLogger): array
    {
        return $this->run(
            request: $request,
            context: $context,
            operation: 'scan_share.resolve',
            payload: $payload,
            callback: fn (AbdmGateway $gateway) => $gateway->resolveScanShare($payload),
            auditLogger: $auditLogger,
        );
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public function linkPatient(
        Request $request,
        TenantContext $context,
        Patient $patient,
        array $profile,
        string $externalTxnId,
        AuditLogger $auditLogger,
    ): Patient {
        abort_unless($patient->hospital_id === $context->hospitalId(), 404);

        $before = $patient->only([
            'abha_id',
            'abha_number',
            'abha_address',
            'abha_verification_status',
            'abha_verified_at',
            'abdm_last_transaction_id',
            'abdm_profile_payload',
        ]);

        $patient->update([
            'abha_id' => $profile['abha_id'] ?? $profile['abha_number'] ?? $patient->abha_id,
            'abha_number' => $profile['abha_number'] ?? $patient->abha_number,
            'abha_address' => $profile['abha_address'] ?? $patient->abha_address,
            'abha_verification_status' => 'verified',
            'abha_verified_at' => now(),
            'abdm_last_transaction_id' => $externalTxnId,
            'abdm_consent_reference' => $profile['consent_reference'] ?? $patient->abdm_consent_reference,
            'abdm_profile_payload' => $profile,
        ]);

        $auditLogger->record(
            request: $request,
            module: 'abdm',
            event: 'patient.abha_linked',
            auditable: $patient,
            old: $before,
            new: $patient->fresh()->only([
                'abha_id',
                'abha_number',
                'abha_address',
                'abha_verification_status',
                'abha_verified_at',
                'abdm_last_transaction_id',
            ]),
        );

        return $patient->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(AbdmGateway): array<string, mixed>  $callback
     * @return array{transaction: AbdmTransaction, result: array<string, mixed>}
     */
    private function run(
        Request $request,
        TenantContext $context,
        string $operation,
        array $payload,
        callable $callback,
        AuditLogger $auditLogger,
    ): array {
        $this->assertEnabled();
        $gateway = $this->gateway();

        $transaction = AbdmTransaction::create([
            'hospital_id' => $context->hospitalId(),
            'branch_id' => $context->branchId(),
            'patient_id' => $payload['patient_id'] ?? null,
            'user_id' => $request->user()?->id,
            'operation' => $operation,
            'provider' => $gateway->providerName(),
            'status' => 'pending',
            'abha_number' => $payload['abha_number'] ?? null,
            'mobile' => $payload['mobile'] ?? null,
            'request_payload' => $payload,
        ]);

        try {
            $result = $callback($gateway);
            $transaction->update([
                'status' => $result['status'] ?? 'completed',
                'external_txn_id' => $result['external_txn_id'] ?? null,
                'abha_number' => $result['profile']['abha_number'] ?? $transaction->abha_number,
                'abha_address' => $result['profile']['abha_address'] ?? null,
                'response_payload' => $result,
                'completed_at' => now(),
            ]);

            $auditLogger->record(
                request: $request,
                module: 'abdm',
                event: $operation,
                auditable: $transaction,
                new: [
                    'provider' => $gateway->providerName(),
                    'status' => $transaction->status,
                    'external_txn_id' => $transaction->external_txn_id,
                ],
            );

            return ['transaction' => $transaction->refresh(), 'result' => $result];
        } catch (Throwable $exception) {
            $transaction->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            $auditLogger->record(
                request: $request,
                module: 'abdm',
                event: $operation.'.failed',
                auditable: $transaction,
                new: ['error' => $exception->getMessage()],
            );

            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException($exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactCreatePayload(array $payload): array
    {
        if (isset($payload['aadhaar_number'])) {
            $digits = preg_replace('/\D+/', '', (string) $payload['aadhaar_number']);
            $payload['aadhaar_number'] = 'XXXX-XXXX-'.substr((string) $digits, -4);
        }

        if (isset($payload['otp'])) {
            $payload['otp'] = '******';
        }

        return $payload;
    }
}
