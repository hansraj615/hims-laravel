<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private const SYSTEM_ROLES = ['platform-admin', 'hospital-admin', 'reception', 'doctor', 'billing'];

    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            request: $request,
            data: RoleResource::collection($roles),
            message: 'Roles loaded',
        );
    }

    public function permissions(Request $request): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            request: $request,
            data: PermissionResource::collection($permissions),
            message: 'Permissions loaded',
        );
    }

    public function store(RoleRequest $request, AuditLogger $auditLogger): JsonResponse
    {
        $name = $request->validated('name');
        $permissions = $request->validated('permissions', []);

        $this->assertRoleManageable($request, $name, $permissions);

        $role = DB::transaction(function () use ($name, $permissions): Role {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);

            return $role;
        });

        $role->load('permissions');

        $auditLogger->record(
            request: $request,
            module: 'admin.roles',
            event: 'role.created',
            auditable: $role,
            new: ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()],
        );

        return ApiResponse::success(
            request: $request,
            data: new RoleResource($role),
            message: 'Role created',
            status: 201,
        );
    }

    public function update(RoleRequest $request, Role $role, AuditLogger $auditLogger): JsonResponse
    {
        $newName = $request->validated('name');
        $permissions = $request->validated('permissions', []);

        $this->assertRoleManageable($request, $role->name, $permissions);

        if (in_array($role->name, self::SYSTEM_ROLES, true) && $newName !== $role->name) {
            throw ValidationException::withMessages([
                'name' => ['System roles cannot be renamed.'],
            ]);
        }

        $old = ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()];

        DB::transaction(function () use ($role, $newName, $permissions): void {
            if (! in_array($role->name, self::SYSTEM_ROLES, true)) {
                $role->update(['name' => $newName]);
            }

            $role->syncPermissions($permissions);
        });

        $role->refresh()->load('permissions');

        $auditLogger->record(
            request: $request,
            module: 'admin.roles',
            event: 'role.updated',
            auditable: $role,
            old: $old,
            new: ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()],
        );

        return ApiResponse::success(
            request: $request,
            data: new RoleResource($role),
            message: 'Role updated',
        );
    }

    /**
     * @param  array<int, string>  $permissions
     *
     * @throws AuthorizationException
     */
    private function assertRoleManageable(Request $request, string $roleName, array $permissions): void
    {
        $actor = $request->user();

        if ($roleName === 'platform-admin' && ! $actor?->hasRole('platform-admin')) {
            throw new AuthorizationException('Only a platform administrator can manage the platform-admin role.');
        }

        if ($actor?->hasRole('platform-admin')) {
            return;
        }

        $allowedPermissions = $actor?->getAllPermissions()->pluck('name')->all() ?? [];
        $disallowed = array_diff($permissions, $allowedPermissions);

        if ($disallowed !== []) {
            throw new AuthorizationException(
                'You cannot assign permissions you do not have: '.implode(', ', $disallowed),
            );
        }
    }
}
