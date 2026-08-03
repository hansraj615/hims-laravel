<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Hospitals\Models\Hospital;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HospitalRequest;
use App\Http\Resources\HospitalResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HospitalController extends Controller
{
    public function index(Request $request, TenantContext $context): JsonResponse
    {
        return ApiResponse::success(
            request: $request,
            data: HospitalResource::collection(collect([$context->hospital])),
            message: 'Hospitals loaded',
        );
    }

    public function update(HospitalRequest $request, TenantContext $context, Hospital $hospital, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($hospital->id === $context->hospitalId(), 404);

        $old = $hospital->only(array_keys($request->validated()));

        $hospital->update($request->validated());
        $hospital->refresh();

        $auditLogger->record(
            request: $request,
            module: 'admin.hospitals',
            event: 'hospital.updated',
            auditable: $hospital,
            old: $old,
            new: $hospital->only(array_keys($request->validated())),
        );

        return ApiResponse::success(
            request: $request,
            data: new HospitalResource($hospital),
            message: 'Hospital updated',
        );
    }
}
