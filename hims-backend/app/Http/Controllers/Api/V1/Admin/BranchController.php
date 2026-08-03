<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Hospitals\Models\Branch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BranchRequest;
use App\Http\Resources\BranchResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $branches = Branch::query()
            ->where('hospital_id', $context->hospitalId())
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            request: $request,
            data: BranchResource::collection($branches),
            message: 'Branches loaded',
        );
    }

    public function store(BranchRequest $request, TenantContext $context): JsonResponse
    {
        $branch = Branch::create([
            ...$request->validated(),
            'hospital_id' => $context->hospitalId(),
        ]);

        return ApiResponse::success(
            request: $request,
            data: new BranchResource($branch),
            message: 'Branch created',
            status: 201,
        );
    }

    public function update(BranchRequest $request, TenantContext $context, Branch $branch): JsonResponse
    {
        abort_unless($branch->hospital_id === $context->hospitalId(), 404);

        $branch->update($request->validated());

        return ApiResponse::success(
            request: $request,
            data: new BranchResource($branch->refresh()),
            message: 'Branch updated',
        );
    }
}
