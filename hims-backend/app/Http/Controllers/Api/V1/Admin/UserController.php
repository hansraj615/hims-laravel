<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Hospitals\Models\UserHospitalBranchAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $users = User::query()
            ->whereHas('hospitalAssignments', fn ($query) => $query
                ->where('hospital_id', $context->hospitalId())
                ->active())
            ->with(['hospitalAssignments' => fn ($query) => $query
                ->where('hospital_id', $context->hospitalId())
                ->active()])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            request: $request,
            data: UserResource::collection($users),
            message: 'Users loaded',
        );
    }

    public function store(UserRequest $request, TenantContext $context, AuditLogger $auditLogger): JsonResponse
    {
        $this->assertRoleAssignable($request, $request->validated('role'));

        $user = DB::transaction(function () use ($request, $context): User {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'mobile' => $request->validated('mobile'),
                'password' => Hash::make($request->validated('password')),
                'status' => $request->validated('status'),
            ]);

            $user->syncRoles([$request->validated('role')]);

            $this->syncAssignment($user, $context, $request);

            return $user;
        });

        $auditLogger->record(
            request: $request,
            module: 'admin.users',
            event: 'user.created',
            auditable: $user,
            new: $user->fresh()->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new UserResource($user->fresh()->load('hospitalAssignments')),
            message: 'User created',
            status: 201,
        );
    }

    public function update(UserRequest $request, TenantContext $context, User $user, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless(
            $user->hospitalAssignments()->where('hospital_id', $context->hospitalId())->exists(),
            404,
        );

        $this->assertRoleAssignable($request, $request->validated('role'));

        $old = $user->toArray();

        DB::transaction(function () use ($request, $context, $user): void {
            $attributes = [
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'mobile' => $request->validated('mobile'),
                'status' => $request->validated('status'),
            ];

            if (filled($request->validated('password'))) {
                $attributes['password'] = Hash::make($request->validated('password'));
            }

            $user->update($attributes);
            $user->syncRoles([$request->validated('role')]);

            $this->syncAssignment($user, $context, $request);
        });

        $user->refresh();

        $auditLogger->record(
            request: $request,
            module: 'admin.users',
            event: 'user.updated',
            auditable: $user,
            old: $old,
            new: $user->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new UserResource($user->load('hospitalAssignments')),
            message: 'User updated',
        );
    }

    private function syncAssignment(User $user, TenantContext $context, UserRequest $request): void
    {
        $attributes = [
            'branch_id' => $request->validated('branch_id'),
            'department_id' => $request->validated('department_id'),
            'assignment_type' => $request->validated('assignment_type') ?? 'staff',
            'is_default' => $request->boolean('is_default', true),
            'status' => 'active',
            'approved_at' => now(),
        ];

        $assignment = UserHospitalBranchAssignment::query()
            ->where('user_id', $user->id)
            ->where('hospital_id', $context->hospitalId())
            ->first();

        if ($assignment) {
            $assignment->update($attributes);

            return;
        }

        UserHospitalBranchAssignment::create([
            'user_id' => $user->id,
            'hospital_id' => $context->hospitalId(),
            ...$attributes,
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    private function assertRoleAssignable(Request $request, string $role): void
    {
        if ($role === 'platform-admin' && ! $request->user()?->hasRole('platform-admin')) {
            throw new AuthorizationException('Only a platform administrator can assign the platform-admin role.');
        }
    }
}
