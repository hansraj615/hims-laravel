<?php

namespace App\Support\Tenancy;

use App\Domain\Hospitals\Models\UserHospitalBranchAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class TenantContextResolver
{
    /**
     * @throws AuthorizationException
     */
    public function resolveForRequest(Request $request, User $user): TenantContext
    {
        $requestedHospitalId = $request->header('X-Hospital-Id');
        $requestedBranchId = $request->header('X-Branch-Id');

        $assignmentQuery = $user->hospitalAssignments()
            ->active()
            ->with(['hospital', 'branch'])
            ->orderByDesc('is_default')
            ->orderBy('id');

        if ($requestedHospitalId !== null) {
            $assignmentQuery->where('hospital_id', (int) $requestedHospitalId);
        }

        if ($requestedBranchId !== null) {
            $assignmentQuery->where('branch_id', (int) $requestedBranchId);
        }

        $assignment = $assignmentQuery->first();

        if (! $assignment) {
            throw new AuthorizationException('No approved hospital or branch assignment is available for this user.');
        }

        return new TenantContext(
            hospital: $assignment->hospital,
            branch: $assignment->branch,
            assignment: $assignment,
        );
    }
}
