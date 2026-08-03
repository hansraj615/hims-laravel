<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\ABDM\Services\AbdmM1Service;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Services\UhidGenerator;
use App\Http\Controllers\Controller;
use App\Http\Resources\PatientResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AbdmController extends Controller
{
    public function status(Request $request, AbdmM1Service $service): JsonResponse
    {
        abort_unless($request->user()->can('abdm.manage'), 403);

        $enabled = (bool) config('abdm.enabled');
        $provider = $enabled ? $service->gateway()->providerName() : 'disabled';

        return ApiResponse::success(
            request: $request,
            data: [
                'enabled' => $enabled,
                'provider' => $provider,
                'mode' => config('abdm.mode'),
                'demo_otp_hint' => $provider === 'simulated' ? 'Configured ABDM_DEMO_OTP (default 123456)' : null,
            ],
            message: 'ABDM status',
        );
    }

    public function initiateVerify(Request $request, TenantContext $context, AbdmM1Service $service, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->can('abdm.manage'), 403);

        $data = $request->validate([
            'abha_number' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
        ]);

        abort_unless(($data['abha_number'] ?? null) || ($data['mobile'] ?? null), 422, 'Provide ABHA number or mobile.');

        try {
            $outcome = $service->initiateVerify($request, $context, $data, $auditLogger);
        } catch (Throwable $exception) {
            return $this->gatewayError($request, $exception);
        }

        return ApiResponse::success(
            request: $request,
            data: [
                'transaction_id' => $outcome['transaction']->id,
                'external_txn_id' => $outcome['result']['external_txn_id'],
                'status' => $outcome['result']['status'],
                'message' => $outcome['result']['message'],
                'provider' => $outcome['transaction']->provider,
            ],
            message: 'ABHA verify initiated',
        );
    }

    public function confirmVerify(Request $request, TenantContext $context, AbdmM1Service $service, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->can('abdm.manage'), 403);

        $data = $request->validate([
            'external_txn_id' => ['required', 'string', 'max:120'],
            'otp' => ['required', 'string', 'max:10'],
            'abha_number' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'link_patient' => ['nullable', 'boolean'],
        ]);

        try {
            $outcome = $service->confirmVerify($request, $context, $data, $auditLogger);
        } catch (Throwable $exception) {
            return $this->gatewayError($request, $exception);
        }

        $patient = null;
        if (($data['link_patient'] ?? false) && ! empty($data['patient_id']) && ! empty($outcome['result']['profile'])) {
            $patientModel = Patient::query()->forHospital($context->hospitalId())->findOrFail($data['patient_id']);
            $patient = $service->linkPatient(
                $request,
                $context,
                $patientModel,
                $outcome['result']['profile'],
                (string) $outcome['result']['external_txn_id'],
                $auditLogger,
            );
        }

        return ApiResponse::success(
            request: $request,
            data: [
                'transaction_id' => $outcome['transaction']->id,
                'external_txn_id' => $outcome['result']['external_txn_id'],
                'status' => $outcome['result']['status'],
                'profile' => $outcome['result']['profile'] ?? null,
                'patient' => $patient ? new PatientResource($patient) : null,
            ],
            message: 'ABHA verify confirmed',
        );
    }

    public function initiateCreate(Request $request, TenantContext $context, AbdmM1Service $service, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->can('abdm.manage'), 403);

        $data = $request->validate([
            'aadhaar_number' => ['required', 'string', 'max:20'],
            'mobile' => ['required', 'string', 'max:20'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
        ]);

        try {
            $outcome = $service->initiateCreate($request, $context, $data, $auditLogger);
        } catch (Throwable $exception) {
            return $this->gatewayError($request, $exception);
        }

        return ApiResponse::success(
            request: $request,
            data: [
                'transaction_id' => $outcome['transaction']->id,
                'external_txn_id' => $outcome['result']['external_txn_id'],
                'status' => $outcome['result']['status'],
                'message' => $outcome['result']['message'],
                'provider' => $outcome['transaction']->provider,
            ],
            message: 'ABHA create initiated',
        );
    }

    public function confirmCreate(Request $request, TenantContext $context, AbdmM1Service $service, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->can('abdm.manage'), 403);

        $data = $request->validate([
            'external_txn_id' => ['required', 'string', 'max:120'],
            'otp' => ['required', 'string', 'max:10'],
            'aadhaar_number' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'link_patient' => ['nullable', 'boolean'],
        ]);

        try {
            $outcome = $service->confirmCreate($request, $context, $data, $auditLogger);
        } catch (Throwable $exception) {
            return $this->gatewayError($request, $exception);
        }

        $patient = null;
        if (($data['link_patient'] ?? false) && ! empty($data['patient_id']) && ! empty($outcome['result']['profile'])) {
            $patientModel = Patient::query()->forHospital($context->hospitalId())->findOrFail($data['patient_id']);
            $patient = $service->linkPatient(
                $request,
                $context,
                $patientModel,
                $outcome['result']['profile'],
                (string) $outcome['result']['external_txn_id'],
                $auditLogger,
            );
        }

        return ApiResponse::success(
            request: $request,
            data: [
                'transaction_id' => $outcome['transaction']->id,
                'external_txn_id' => $outcome['result']['external_txn_id'],
                'status' => $outcome['result']['status'],
                'profile' => $outcome['result']['profile'] ?? null,
                'patient' => $patient ? new PatientResource($patient) : null,
            ],
            message: 'ABHA create confirmed',
        );
    }

    public function scanShare(
        Request $request,
        TenantContext $context,
        AbdmM1Service $service,
        AuditLogger $auditLogger,
        UhidGenerator $uhidGenerator,
    ): JsonResponse {
        abort_unless($request->user()->can('abdm.manage'), 403);

        $data = $request->validate([
            'share_code' => ['required', 'string', 'max:200'],
            'counter_id' => ['nullable', 'string', 'max:40'],
            'register_patient' => ['nullable', 'boolean'],
        ]);

        try {
            $outcome = $service->scanShare($request, $context, $data, $auditLogger);
        } catch (Throwable $exception) {
            return $this->gatewayError($request, $exception);
        }

        $profile = $outcome['result']['profile'] ?? [];
        $patient = null;

        if (($data['register_patient'] ?? false) && ! empty($profile)) {
            $patient = DB::transaction(function () use ($request, $context, $profile, $outcome, $uhidGenerator, $auditLogger, $service) {
                $patient = Patient::create([
                    'hospital_id' => $context->hospitalId(),
                    'branch_id' => $context->branchId(),
                    'uhid' => $uhidGenerator->nextForHospital($context->hospital),
                    'registration_source' => 'online',
                    'first_name' => $profile['first_name'] ?? 'Scan',
                    'last_name' => $profile['last_name'] ?? 'Share',
                    'gender' => in_array(($profile['gender'] ?? 'unknown'), ['male', 'female', 'other', 'unknown'], true)
                        ? $profile['gender']
                        : 'unknown',
                    'mobile' => $profile['mobile'] ?? null,
                    'status' => 'active',
                    'registered_by' => $request->user()->id,
                    'registered_at' => now(),
                    'abdm_scan_share_payload' => $outcome['result'],
                ]);

                return $service->linkPatient(
                    $request,
                    $context,
                    $patient,
                    $profile,
                    (string) $outcome['result']['external_txn_id'],
                    $auditLogger,
                );
            });
        }

        return ApiResponse::success(
            request: $request,
            data: [
                'transaction_id' => $outcome['transaction']->id,
                'external_txn_id' => $outcome['result']['external_txn_id'],
                'status' => $outcome['result']['status'],
                'profile' => $profile,
                'patient' => $patient ? new PatientResource($patient) : null,
            ],
            message: 'Scan & Share resolved',
            status: $patient ? 201 : 200,
        );
    }

    public function linkPatient(Request $request, TenantContext $context, Patient $patient, AbdmM1Service $service, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($request->user()->can('abdm.manage'), 403);
        abort_unless($patient->hospital_id === $context->hospitalId(), 404);

        $data = $request->validate([
            'external_txn_id' => ['required', 'string', 'max:120'],
            'profile' => ['required', 'array'],
            'profile.abha_number' => ['nullable', 'string', 'max:30'],
            'profile.abha_address' => ['nullable', 'string', 'max:120'],
            'profile.abha_id' => ['nullable', 'string', 'max:80'],
        ]);

        $service->assertEnabled();

        $patient = $service->linkPatient(
            $request,
            $context,
            $patient,
            $data['profile'],
            $data['external_txn_id'],
            $auditLogger,
        );

        return ApiResponse::success(
            request: $request,
            data: new PatientResource($patient),
            message: 'ABHA linked to patient',
        );
    }

    private function gatewayError(Request $request, Throwable $exception): JsonResponse
    {
        $status = $exception instanceof RuntimeException && str_contains($exception->getMessage(), 'disabled')
            ? 503
            : 422;

        return ApiResponse::error(
            request: $request,
            message: $exception->getMessage(),
            errors: [],
            status: $status,
        );
    }
}
