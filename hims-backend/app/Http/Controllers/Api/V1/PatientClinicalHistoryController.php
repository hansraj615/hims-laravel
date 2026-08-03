<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\EMR\Models\Encounter;
use App\Domain\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use App\Http\Resources\PatientClinicalHistoryEncounterResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientClinicalHistoryController extends Controller
{
    public function index(Request $request, TenantContext $context, Patient $patient): JsonResponse
    {
        $this->authorizeHistoryAccess($request);
        abort_unless($patient->hospital_id === $context->hospitalId(), 404);

        $limit = min(max((int) $request->integer('limit', 20), 1), 50);
        $excludeEncounterId = $request->filled('exclude_encounter_id')
            ? $request->integer('exclude_encounter_id')
            : null;

        $encounters = Encounter::query()
            ->forHospital($context->hospitalId())
            ->where('patient_id', $patient->id)
            ->when($excludeEncounterId !== null, fn ($query) => $query->where('id', '!=', $excludeEncounterId))
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
                fn ($query) => $query->where('status', 'completed'),
            )
            ->with([
                'doctor:id,name',
                'department:id,name,code',
                'prescriptions.items',
            ])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: [
                'patient_id' => $patient->id,
                'uhid' => $patient->uhid,
                'encounters' => PatientClinicalHistoryEncounterResource::collection($encounters),
            ],
            message: 'Clinical history loaded',
            meta: [
                'excluded_encounter_id' => $excludeEncounterId,
                'count' => $encounters->count(),
            ],
        );
    }

    private function authorizeHistoryAccess(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null && ($user->can('opd.consult') || $user->can('patients.manage')),
            403,
            'You do not have permission to view clinical history.',
        );
    }
}
