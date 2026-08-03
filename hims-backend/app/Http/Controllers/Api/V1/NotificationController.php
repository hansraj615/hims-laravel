<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notifications\Models\NotificationLog;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationLogResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $logs = NotificationLog::query()
            ->forHospital($context->hospitalId())
            ->where('user_id', $request->user()?->id)
            ->latest('id')
            ->limit(50)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: NotificationLogResource::collection($logs),
            message: 'Notifications loaded',
        );
    }
}
