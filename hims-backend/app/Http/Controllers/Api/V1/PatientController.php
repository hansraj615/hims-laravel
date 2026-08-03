<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Services\UhidGenerator;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientRequest;
use App\Http\Resources\PatientResource;
use App\Support\ApiResponse;
use App\Support\PhoneNumber;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $patients = Patient::query()
            ->forHospital($context->hospitalId())
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('branch_id'), fn (Builder $query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('q'), fn (Builder $query) => $this->applySearch($query, (string) $request->query('q')))
            ->latest('id')
            ->limit(100)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: PatientResource::collection($patients),
            message: 'Patients loaded',
        );
    }

    public function duplicates(Request $request, TenantContext $context): JsonResponse
    {
        $hasDuplicateSignal = $request->filled('mobile')
            || ($request->filled('identity_type') && $request->filled('identity_number'))
            || $request->filled('abha_id')
            || $request->filled('name');

        $patients = Patient::query()
            ->forHospital($context->hospitalId())
            ->when($hasDuplicateSignal, function (Builder $query) use ($request): void {
                $query->where(function (Builder $query) use ($request): void {
                    if ($request->filled('mobile')) {
                        $query->orWhere('mobile', PhoneNumber::normalizeIndianMobile((string) $request->query('mobile')));
                    }

                    if ($request->filled('identity_type') && $request->filled('identity_number')) {
                        $query->orWhere(function (Builder $query) use ($request): void {
                            $query
                                ->where('identity_type', $request->query('identity_type'))
                                ->where('identity_number', $request->query('identity_number'));
                        });
                    }

                    if ($request->filled('abha_id')) {
                        $query->orWhere('abha_id', $request->query('abha_id'));
                    }

                    if ($request->filled('name')) {
                        $term = (string) $request->query('name');
                        $query
                            ->orWhere('first_name', 'like', "%{$term}%")
                            ->orWhere('middle_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    }
                });
            })
            ->when(! $hasDuplicateSignal, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->latest('id')
            ->limit(10)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: PatientResource::collection($patients),
            message: 'Duplicate candidates loaded',
        );
    }

    public function store(PatientRequest $request, TenantContext $context, UhidGenerator $uhidGenerator, AuditLogger $auditLogger): JsonResponse
    {
        $patient = DB::transaction(function () use ($request, $context, $uhidGenerator): Patient {
            return Patient::create([
                ...$request->validated(),
                'hospital_id' => $context->hospitalId(),
                'branch_id' => $request->validated('branch_id') ?? $context->branchId(),
                'uhid' => $uhidGenerator->nextForHospital($context->hospital),
                'registered_at' => now(),
                'registered_by' => $request->user()?->id,
            ]);
        });

        $auditLogger->record(
            request: $request,
            module: 'patients',
            event: 'patient.created',
            auditable: $patient,
            new: $patient->toArray(),
        );

        app(NotificationDispatcher::class)->dispatch(
            hospitalId: $context->hospitalId(),
            branchId: $context->branchId(),
            templateCode: 'patient.registered',
            channel: 'in_app',
            recipient: $request->user()?->email,
            context: ['patient_name' => $patient->full_name, 'uhid' => $patient->uhid],
            patientId: $patient->id,
            userId: $request->user()?->id,
            related: $patient,
        );

        return ApiResponse::success(
            request: $request,
            data: new PatientResource($patient),
            message: 'Patient registered',
            status: 201,
        );
    }

    public function show(Request $request, TenantContext $context, Patient $patient): JsonResponse
    {
        abort_unless($patient->hospital_id === $context->hospitalId(), 404);

        return ApiResponse::success(
            request: $request,
            data: new PatientResource($patient),
            message: 'Patient loaded',
        );
    }

    public function update(PatientRequest $request, TenantContext $context, Patient $patient, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($patient->hospital_id === $context->hospitalId(), 404);

        $old = $patient->toArray();

        $patient->update([
            ...$request->validated(),
            'branch_id' => $request->validated('branch_id') ?? $context->branchId(),
        ]);

        $patient->refresh();

        $auditLogger->record(
            request: $request,
            module: 'patients',
            event: 'patient.updated',
            auditable: $patient,
            old: $old,
            new: $patient->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new PatientResource($patient),
            message: 'Patient updated',
        );
    }

    private function applySearch(Builder $query, string $term): void
    {
        $normalizedMobile = PhoneNumber::normalizeIndianMobile($term);

        $query->where(function (Builder $query) use ($term, $normalizedMobile): void {
            $query
                ->where('uhid', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('middle_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$normalizedMobile}%")
                ->orWhere('abha_id', 'like', "%{$term}%")
                ->orWhere('identity_number', 'like', "%{$term}%");
        });
    }
}
