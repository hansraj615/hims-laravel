<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Hospitals\Models\Department;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $departments = Department::query()
            ->forHospital($context->hospitalId())
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            request: $request,
            data: DepartmentResource::collection($departments),
            message: 'Departments loaded',
        );
    }

    public function store(DepartmentRequest $request, TenantContext $context): JsonResponse
    {
        $department = Department::create([
            ...$request->validated(),
            'hospital_id' => $context->hospitalId(),
        ]);

        return ApiResponse::success(
            request: $request,
            data: new DepartmentResource($department),
            message: 'Department created',
            status: 201,
        );
    }

    public function update(DepartmentRequest $request, TenantContext $context, Department $department): JsonResponse
    {
        abort_unless($department->hospital_id === $context->hospitalId(), 404);

        $department->update($request->validated());

        return ApiResponse::success(
            request: $request,
            data: new DepartmentResource($department->refresh()),
            message: 'Department updated',
        );
    }
}
