<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Appointments\Models\DoctorFeeMaster;
use App\Domain\Appointments\Models\DoctorLeave;
use App\Domain\Appointments\Models\DoctorSchedule;
use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DoctorFeeRequest;
use App\Http\Requests\Admin\DoctorLeaveRequest;
use App\Http\Requests\Admin\DoctorScheduleRequest;
use App\Http\Resources\DoctorFeeResource;
use App\Http\Resources\DoctorLeaveResource;
use App\Http\Resources\DoctorScheduleResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorOpsController extends Controller
{
    public function schedules(Request $request, TenantContext $context, User $doctor): JsonResponse
    {
        $this->assertDoctorInHospital($context, $doctor);

        $items = DoctorSchedule::query()
            ->forHospital($context->hospitalId())
            ->where('doctor_user_id', $doctor->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return ApiResponse::success(
            request: $request,
            data: DoctorScheduleResource::collection($items),
            message: 'Doctor schedules loaded',
        );
    }

    public function storeSchedule(DoctorScheduleRequest $request, TenantContext $context, User $doctor, AuditLogger $auditLogger): JsonResponse
    {
        $this->assertDoctorInHospital($context, $doctor);
        $data = $request->validated();

        $schedule = DoctorSchedule::create([
            'hospital_id' => $context->hospitalId(),
            'branch_id' => $data['branch_id'] ?? $context->branchId(),
            'doctor_user_id' => $doctor->id,
            'department_id' => $data['department_id'] ?? null,
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'slot_duration_minutes' => $data['slot_duration_minutes'] ?? 15,
            'status' => $data['status'] ?? 'active',
        ]);

        $auditLogger->record(
            request: $request,
            module: 'admin.doctor_ops',
            event: 'doctor.schedule.created',
            auditable: $schedule,
            new: $schedule->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new DoctorScheduleResource($schedule),
            message: 'Doctor schedule created',
            status: 201,
        );
    }

    public function updateSchedule(DoctorScheduleRequest $request, TenantContext $context, User $doctor, DoctorSchedule $schedule, AuditLogger $auditLogger): JsonResponse
    {
        $this->assertOwnedSchedule($context, $doctor, $schedule);
        $old = $schedule->toArray();
        $data = $request->validated();

        $schedule->update([
            'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $schedule->branch_id,
            'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : $schedule->department_id,
            'day_of_week' => $data['day_of_week'] ?? $schedule->day_of_week,
            'start_time' => $data['start_time'] ?? $schedule->start_time,
            'end_time' => $data['end_time'] ?? $schedule->end_time,
            'slot_duration_minutes' => $data['slot_duration_minutes'] ?? $schedule->slot_duration_minutes,
            'status' => $data['status'] ?? $schedule->status,
        ]);

        $auditLogger->record(
            request: $request,
            module: 'admin.doctor_ops',
            event: 'doctor.schedule.updated',
            auditable: $schedule,
            old: $old,
            new: $schedule->fresh()->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new DoctorScheduleResource($schedule->refresh()),
            message: 'Doctor schedule updated',
        );
    }

    public function leaves(Request $request, TenantContext $context, User $doctor): JsonResponse
    {
        $this->assertDoctorInHospital($context, $doctor);

        $items = DoctorLeave::query()
            ->forHospital($context->hospitalId())
            ->where('doctor_user_id', $doctor->id)
            ->orderByDesc('start_date')
            ->get();

        return ApiResponse::success(
            request: $request,
            data: DoctorLeaveResource::collection($items),
            message: 'Doctor leaves loaded',
        );
    }

    public function storeLeave(DoctorLeaveRequest $request, TenantContext $context, User $doctor, AuditLogger $auditLogger): JsonResponse
    {
        $this->assertDoctorInHospital($context, $doctor);
        $data = $request->validated();

        $leave = DoctorLeave::create([
            'hospital_id' => $context->hospitalId(),
            'doctor_user_id' => $doctor->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        $auditLogger->record(
            request: $request,
            module: 'admin.doctor_ops',
            event: 'doctor.leave.created',
            auditable: $leave,
            new: $leave->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new DoctorLeaveResource($leave),
            message: 'Doctor leave created',
            status: 201,
        );
    }

    public function updateLeave(DoctorLeaveRequest $request, TenantContext $context, User $doctor, DoctorLeave $leave, AuditLogger $auditLogger): JsonResponse
    {
        $this->assertOwnedLeave($context, $doctor, $leave);
        $old = $leave->toArray();
        $data = $request->validated();

        $leave->update([
            'start_date' => $data['start_date'] ?? $leave->start_date,
            'end_date' => $data['end_date'] ?? $leave->end_date,
            'reason' => array_key_exists('reason', $data) ? $data['reason'] : $leave->reason,
            'status' => $data['status'] ?? $leave->status,
        ]);

        $auditLogger->record(
            request: $request,
            module: 'admin.doctor_ops',
            event: 'doctor.leave.updated',
            auditable: $leave,
            old: $old,
            new: $leave->fresh()->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new DoctorLeaveResource($leave->refresh()),
            message: 'Doctor leave updated',
        );
    }

    public function fees(Request $request, TenantContext $context, User $doctor): JsonResponse
    {
        $this->assertDoctorInHospital($context, $doctor);

        $items = DoctorFeeMaster::query()
            ->forHospital($context->hospitalId())
            ->where('doctor_user_id', $doctor->id)
            ->orderBy('visit_type')
            ->get();

        return ApiResponse::success(
            request: $request,
            data: DoctorFeeResource::collection($items),
            message: 'Doctor fees loaded',
        );
    }

    public function upsertFee(DoctorFeeRequest $request, TenantContext $context, User $doctor, AuditLogger $auditLogger): JsonResponse
    {
        $this->assertDoctorInHospital($context, $doctor);
        $data = $request->validated();

        $fee = DoctorFeeMaster::updateOrCreate(
            [
                'hospital_id' => $context->hospitalId(),
                'doctor_user_id' => $doctor->id,
                'visit_type' => $data['visit_type'],
            ],
            [
                'fee_amount' => $data['fee_amount'],
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
                'status' => $data['status'] ?? 'active',
            ],
        );

        $auditLogger->record(
            request: $request,
            module: 'admin.doctor_ops',
            event: 'doctor.fee.upserted',
            auditable: $fee,
            new: $fee->toArray(),
        );

        return ApiResponse::success(
            request: $request,
            data: new DoctorFeeResource($fee),
            message: 'Doctor fee saved',
        );
    }

    private function assertDoctorInHospital(TenantContext $context, User $doctor): void
    {
        abort_unless($doctor->hasRole('doctor'), 404);

        $assigned = $doctor->hospitalAssignments()
            ->where('hospital_id', $context->hospitalId())
            ->where('status', 'active')
            ->exists();

        abort_unless($assigned, 404);
    }

    private function assertOwnedSchedule(TenantContext $context, User $doctor, DoctorSchedule $schedule): void
    {
        $this->assertDoctorInHospital($context, $doctor);
        abort_unless(
            $schedule->hospital_id === $context->hospitalId() && $schedule->doctor_user_id === $doctor->id,
            404,
        );
    }

    private function assertOwnedLeave(TenantContext $context, User $doctor, DoctorLeave $leave): void
    {
        $this->assertDoctorInHospital($context, $doctor);
        abort_unless(
            $leave->hospital_id === $context->hospitalId() && $leave->doctor_user_id === $doctor->id,
            404,
        );
    }
}
