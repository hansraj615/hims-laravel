<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentContextResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    public function show(Request $request, TenantContext $context): JsonResponse
    {
        return ApiResponse::success(
            request: $request,
            data: new CurrentContextResource($context),
            message: 'Current hospital context loaded',
        );
    }
}
