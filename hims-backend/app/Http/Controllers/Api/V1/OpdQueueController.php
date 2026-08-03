<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\OPD\Models\OpdQueue;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpdQueueVitalsRequest;
use App\Http\Resources\OpdQueueResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpdQueueController extends Controller
{
    private const VITALS_EDITABLE_STATUSES = ['waiting', 'called', 'skipped', 'in_consultation'];

    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $this->authorizeQueueView($request);

        $queues = OpdQueue::query()
            ->forHospital($context->hospitalId())
            ->with(['patient:id,uhid,first_name,middle_name,last_name,mobile', 'doctor:id,name', 'department:id,name,code'])
            ->when($request->filled('date'), fn (Builder $query) => $query->whereDate('queue_date', $request->date('date')))
            ->when(! $request->filled('date'), fn (Builder $query) => $query->whereDate('queue_date', today()))
            ->when($request->filled('department_id'), fn (Builder $query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('doctor_user_id'), fn (Builder $query) => $query->where('doctor_user_id', $request->integer('doctor_user_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->orderBy('token_number')
            ->limit(200)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: OpdQueueResource::collection($queues),
            message: 'OPD queue loaded',
        );
    }

    public function call(Request $request, TenantContext $context, OpdQueue $opdQueue): JsonResponse
    {
        $this->authorizeQueueManage($request);
        abort_unless($opdQueue->hospital_id === $context->hospitalId(), 404);
        abort_unless($opdQueue->status === 'waiting', 422, 'Only waiting tokens can be called.');

        $opdQueue->update([
            'status' => 'called',
            'called_at' => now(),
        ]);

        return ApiResponse::success(
            request: $request,
            data: new OpdQueueResource($opdQueue->refresh()->load(['patient', 'doctor', 'department'])),
            message: 'Token called',
        );
    }

    public function start(Request $request, TenantContext $context, OpdQueue $opdQueue): JsonResponse
    {
        $this->authorizeQueueManage($request);
        abort_unless($opdQueue->hospital_id === $context->hospitalId(), 404);
        abort_unless($opdQueue->status === 'called', 422, 'Only called tokens can start consultation.');

        $opdQueue->update([
            'status' => 'in_consultation',
            'started_at' => now(),
        ]);

        return ApiResponse::success(
            request: $request,
            data: new OpdQueueResource($opdQueue->refresh()->load(['patient', 'doctor', 'department'])),
            message: 'Consultation started',
        );
    }

    public function complete(Request $request, TenantContext $context, OpdQueue $opdQueue): JsonResponse
    {
        $this->authorizeQueueManage($request);
        abort_unless($opdQueue->hospital_id === $context->hospitalId(), 404);
        abort_unless($opdQueue->status === 'in_consultation', 422, 'Only tokens in consultation can be completed.');

        $opdQueue->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return ApiResponse::success(
            request: $request,
            data: new OpdQueueResource($opdQueue->refresh()->load(['patient', 'doctor', 'department'])),
            message: 'Consultation completed',
        );
    }

    public function skip(Request $request, TenantContext $context, OpdQueue $opdQueue): JsonResponse
    {
        $this->authorizeQueueManage($request);
        abort_unless($opdQueue->hospital_id === $context->hospitalId(), 404);
        abort_unless(in_array($opdQueue->status, ['waiting', 'called'], true), 422, 'Only waiting or called tokens can be skipped.');

        $opdQueue->update([
            'status' => 'skipped',
            'called_at' => $opdQueue->called_at ?? now(),
            'started_at' => null,
        ]);

        return ApiResponse::success(
            request: $request,
            data: new OpdQueueResource($opdQueue->refresh()->load(['patient', 'doctor', 'department'])),
            message: 'Token skipped',
        );
    }

    public function requeue(Request $request, TenantContext $context, OpdQueue $opdQueue): JsonResponse
    {
        $this->authorizeQueueManage($request);
        abort_unless($opdQueue->hospital_id === $context->hospitalId(), 404);
        abort_unless(in_array($opdQueue->status, ['skipped', 'called'], true), 422, 'Only skipped or called tokens can be requeued.');

        $opdQueue->update([
            'status' => 'waiting',
            'called_at' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);

        return ApiResponse::success(
            request: $request,
            data: new OpdQueueResource($opdQueue->refresh()->load(['patient', 'doctor', 'department'])),
            message: 'Token requeued',
        );
    }

    public function showVitals(Request $request, TenantContext $context, OpdQueue $opdQueue): JsonResponse
    {
        $this->authorizeQueueView($request);
        abort_unless($opdQueue->hospital_id === $context->hospitalId(), 404);

        return ApiResponse::success(
            request: $request,
            data: [
                'opd_queue_id' => $opdQueue->id,
                'vitals' => $opdQueue->vitals,
                'vitals_recorded_at' => $opdQueue->vitals_recorded_at?->toISOString(),
                'vitals_recorded_by' => $opdQueue->vitals_recorded_by,
            ],
            message: 'Queue vitals loaded',
        );
    }

    public function updateVitals(
        OpdQueueVitalsRequest $request,
        TenantContext $context,
        OpdQueue $opdQueue,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $this->authorizeVitalsWrite($request);
        abort_unless($opdQueue->hospital_id === $context->hospitalId(), 404);
        abort_unless(
            in_array($opdQueue->status, self::VITALS_EDITABLE_STATUSES, true),
            422,
            'Vitals can only be updated while the patient is waiting, called or in consultation.',
        );

        $old = $opdQueue->vitals;
        $vitals = $request->validated('vitals');

        $opdQueue->update([
            'vitals' => $vitals,
            'vitals_recorded_at' => now(),
            'vitals_recorded_by' => $request->user()?->id,
        ]);

        $auditLogger->record(
            request: $request,
            module: 'opd_queue',
            event: 'queue.vitals.updated',
            auditable: $opdQueue,
            old: ['vitals' => $old],
            new: ['vitals' => $opdQueue->vitals],
        );

        return ApiResponse::success(
            request: $request,
            data: new OpdQueueResource($opdQueue->refresh()->load(['patient', 'doctor', 'department'])),
            message: 'Vitals recorded',
        );
    }

    private function authorizeQueueView(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null && (
                $user->can('appointments.manage')
                || $user->can('opd.consult')
                || $user->can('opd.vitals')
            ),
            403,
            'You do not have permission to access the OPD queue.',
        );
    }

    private function authorizeQueueManage(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null && ($user->can('appointments.manage') || $user->can('opd.consult')),
            403,
            'You do not have permission to manage the OPD queue.',
        );
    }

    private function authorizeVitalsWrite(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null && ($user->can('opd.vitals') || $user->can('opd.consult')),
            403,
            'You do not have permission to record vitals.',
        );
    }
}
