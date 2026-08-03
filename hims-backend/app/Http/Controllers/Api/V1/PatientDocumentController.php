<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientDocumentRequest;
use App\Http\Resources\PatientDocumentResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientDocumentController extends Controller
{
    public function index(Request $request, TenantContext $context, Patient $patient): JsonResponse
    {
        abort_unless($patient->hospital_id === $context->hospitalId(), 404);

        $documents = $patient->documents()->latest('id')->get();

        return ApiResponse::success(
            request: $request,
            data: PatientDocumentResource::collection($documents),
            message: 'Patient documents loaded',
        );
    }

    public function store(PatientDocumentRequest $request, TenantContext $context, Patient $patient, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($patient->hospital_id === $context->hospitalId(), 404);

        $document = $patient->documents()->create([
            ...$request->validated(),
            'hospital_id' => $context->hospitalId(),
            'uploaded_by' => $request->user()?->id,
        ]);

        $auditLogger->record(
            request: $request,
            module: 'patients',
            event: 'patient.document.created',
            auditable: $document,
            new: $document->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new PatientDocumentResource($document),
            message: 'Patient document added',
            status: 201,
        );
    }
}
