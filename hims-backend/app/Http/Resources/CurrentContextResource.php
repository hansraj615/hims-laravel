<?php

namespace App\Http\Resources;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentContextResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TenantContext $context */
        $context = $this->resource;
        $user = $request->user();

        $assignments = $user === null
            ? collect()
            : $user->hospitalAssignments()
                ->active()
                ->with(['hospital:id,name,code,status', 'branch:id,name,code,timezone,status'])
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get();

        return [
            'hospital' => [
                'id' => $context->hospital->id,
                'name' => $context->hospital->name,
                'code' => $context->hospital->code,
                'status' => $context->hospital->status,
            ],
            'branch' => $context->branch ? [
                'id' => $context->branch->id,
                'name' => $context->branch->name,
                'code' => $context->branch->code,
                'timezone' => $context->branch->timezone,
                'status' => $context->branch->status,
            ] : null,
            'assignment' => [
                'id' => $context->assignment->id,
                'type' => $context->assignment->assignment_type,
                'is_default' => $context->assignment->is_default,
            ],
            'available_assignments' => $assignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'hospital' => [
                    'id' => $assignment->hospital?->id,
                    'name' => $assignment->hospital?->name,
                    'code' => $assignment->hospital?->code,
                    'status' => $assignment->hospital?->status,
                ],
                'branch' => $assignment->branch ? [
                    'id' => $assignment->branch->id,
                    'name' => $assignment->branch->name,
                    'code' => $assignment->branch->code,
                    'timezone' => $assignment->branch->timezone,
                    'status' => $assignment->branch->status,
                ] : null,
                'is_default' => $assignment->is_default,
            ])->values()->all(),
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
                'permissions' => $user?->getAllPermissions()->pluck('name')->values()->all() ?? [],
                'roles' => $user?->getRoleNames()->values()->all() ?? [],
            ],
        ];
    }
}
