<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Models\Service;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCatalogController extends Controller
{
    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $services = Service::query()
            ->forHospital($context->hospitalId())
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when(! $request->filled('status'), fn (Builder $query) => $query->where('status', 'active'))
            ->when($request->filled('service_type'), fn (Builder $query) => $query->where('service_type', $request->string('service_type')))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')))
            ->when($request->filled('q'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->query('q').'%'))
            ->orderBy('name')
            ->limit(200)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: ServiceResource::collection($services),
            message: 'Services loaded',
        );
    }

    public function store(ServiceRequest $request, TenantContext $context): JsonResponse
    {
        $service = Service::create([
            ...$request->validated(),
            'hospital_id' => $context->hospitalId(),
            'is_tax_exempt' => $request->boolean('is_tax_exempt', true),
            'status' => $request->validated('status') ?? 'active',
        ]);

        return ApiResponse::success(
            request: $request,
            data: new ServiceResource($service),
            message: 'Service created',
            status: 201,
        );
    }

    public function update(ServiceRequest $request, TenantContext $context, Service $service): JsonResponse
    {
        abort_unless($service->hospital_id === $context->hospitalId(), 404);

        $service->update([
            ...$request->validated(),
            'is_tax_exempt' => $request->has('is_tax_exempt') ? $request->boolean('is_tax_exempt') : $service->is_tax_exempt,
            'status' => $request->validated('status') ?? $service->status,
        ]);

        return ApiResponse::success(
            request: $request,
            data: new ServiceResource($service->refresh()),
            message: 'Service updated',
        );
    }
}
