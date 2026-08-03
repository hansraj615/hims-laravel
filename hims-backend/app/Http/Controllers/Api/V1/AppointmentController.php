<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Services\AppointmentNumberGenerator;
use App\Domain\Appointments\Services\SlotAvailabilityService;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Hospitals\Models\Department;
use App\Domain\OPD\Models\OpdQueue;
use App\Domain\OPD\Services\TokenGenerator;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentCancelRequest;
use App\Http\Requests\AppointmentRequest;
use App\Http\Requests\AppointmentRescheduleRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    private const RESCHEDULABLE_STATUSES = ['booked', 'confirmed'];

    private const CHECK_IN_BLOCKED_STATUSES = ['cancelled', 'no_show', 'checked_in', 'completed'];

    public function index(Request $request, TenantContext $context): JsonResponse
    {
        $appointments = Appointment::query()
            ->forHospital($context->hospitalId())
            ->with(['patient:id,uhid,first_name,middle_name,last_name,mobile', 'doctor:id,name', 'department:id,name,code'])
            ->when($request->filled('date'), fn (Builder $query) => $query->whereDate('appointment_date', $request->date('date')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('doctor_user_id'), fn (Builder $query) => $query->where('doctor_user_id', $request->integer('doctor_user_id')))
            ->when($request->filled('patient_id'), fn (Builder $query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('branch_id'), fn (Builder $query) => $query->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('appointment_date')
            ->orderBy('slot_start')
            ->limit(200)
            ->get();

        return ApiResponse::success(
            request: $request,
            data: AppointmentResource::collection($appointments),
            message: 'Appointments loaded',
        );
    }

    public function options(Request $request, TenantContext $context): JsonResponse
    {
        $departments = Department::query()
            ->where('hospital_id', $context->hospitalId())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'branch_id']);

        $doctors = User::query()
            ->role('doctor')
            ->where('status', 'active')
            ->whereHas('hospitalAssignments', fn (Builder $query) => $query
                ->where('hospital_id', $context->hospitalId())
                ->active())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return ApiResponse::success(
            request: $request,
            data: [
                'departments' => $departments,
                'doctors' => $doctors,
            ],
            message: 'Appointment booking options loaded',
        );
    }

    public function slots(Request $request, TenantContext $context, SlotAvailabilityService $slots): JsonResponse
    {
        $validated = $request->validate([
            'doctor_user_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'visit_type' => ['nullable', 'in:first_visit,follow_up,emergency'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $availability = $slots->availability(
            hospitalId: $context->hospitalId(),
            doctorUserId: (int) $validated['doctor_user_id'],
            date: $validated['date'],
            visitType: $validated['visit_type'] ?? 'first_visit',
            branchId: isset($validated['branch_id']) ? (int) $validated['branch_id'] : $context->branchId(),
        );

        return ApiResponse::success(
            request: $request,
            data: $availability,
            message: 'Available slots loaded',
        );
    }

    public function store(
        AppointmentRequest $request,
        TenantContext $context,
        AppointmentNumberGenerator $generator,
        AuditLogger $auditLogger,
        SlotAvailabilityService $slots,
    ): JsonResponse {
        $data = $request->validated();
        $visitType = $data['visit_type'] ?? 'first_visit';
        $branchId = $data['branch_id'] ?? $context->branchId();
        $doctorUserId = $data['doctor_user_id'] ?? null;

        if ($doctorUserId !== null && ! empty($data['slot_start']) && ! empty($data['slot_end'])) {
            $slots->assertBookable(
                hospitalId: $context->hospitalId(),
                doctorUserId: (int) $doctorUserId,
                date: $data['appointment_date'],
                slotStart: $data['slot_start'],
                slotEnd: $data['slot_end'],
                visitType: $visitType,
                branchId: $branchId !== null ? (int) $branchId : null,
            );
        } elseif ($doctorUserId !== null && $slots->doctorUsesSchedules($context->hospitalId(), (int) $doctorUserId)) {
            throw ValidationException::withMessages([
                'slot_start' => ['A schedule slot is required for this doctor.'],
            ]);
        }

        $feeAmount = $data['fee_amount'] ?? null;
        if (($feeAmount === null || (float) $feeAmount <= 0) && $doctorUserId !== null) {
            $resolved = $slots->resolveFee($context->hospitalId(), (int) $doctorUserId, $visitType, $data['appointment_date']);
            if ($resolved !== null) {
                $feeAmount = $resolved;
            }
        }

        $appointment = DB::transaction(function () use ($data, $request, $context, $generator, $visitType, $branchId, $doctorUserId, $feeAmount): Appointment {
            return Appointment::create([
                'hospital_id' => $context->hospitalId(),
                'branch_id' => $branchId,
                'patient_id' => $data['patient_id'],
                'department_id' => $data['department_id'] ?? null,
                'doctor_user_id' => $doctorUserId,
                'appointment_number' => $generator->nextForHospital($context->hospital),
                'appointment_date' => $data['appointment_date'],
                'slot_start' => $data['slot_start'] ?? null,
                'slot_end' => $data['slot_end'] ?? null,
                'visit_type' => $visitType,
                'source' => $data['source'] ?? 'walk_in',
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'booked',
                'fee_amount' => $feeAmount ?? 0,
                'payment_status' => 'not_billed',
                'reason' => $data['reason'] ?? null,
                'created_by' => $request->user()?->id,
            ]);
        });

        $auditLogger->record(
            request: $request,
            module: 'appointments',
            event: 'appointment.booked',
            auditable: $appointment,
            new: $appointment->only(['appointment_number', 'status', 'appointment_date', 'doctor_user_id', 'department_id']),
        );

        return ApiResponse::success(
            request: $request,
            data: new AppointmentResource($appointment->load(['patient', 'doctor', 'department'])),
            message: 'Appointment booked',
            status: 201,
        );
    }

    public function show(Request $request, TenantContext $context, Appointment $appointment): JsonResponse
    {
        abort_unless($appointment->hospital_id === $context->hospitalId(), 404);

        return ApiResponse::success(
            request: $request,
            data: new AppointmentResource($appointment->load(['patient', 'doctor', 'department'])),
            message: 'Appointment loaded',
        );
    }

    public function update(
        AppointmentRescheduleRequest $request,
        TenantContext $context,
        Appointment $appointment,
        SlotAvailabilityService $slots,
    ): JsonResponse {
        abort_unless($appointment->hospital_id === $context->hospitalId(), 404);
        abort_unless(in_array($appointment->status, self::RESCHEDULABLE_STATUSES, true), 422, 'Only booked or confirmed appointments can be rescheduled.');

        $data = $request->validated();
        $doctorUserId = array_key_exists('doctor_user_id', $data) ? $data['doctor_user_id'] : $appointment->doctor_user_id;
        $appointmentDate = $data['appointment_date'] ?? $appointment->appointment_date?->toDateString();
        $slotStart = array_key_exists('slot_start', $data) ? $data['slot_start'] : $appointment->slot_start;
        $slotEnd = array_key_exists('slot_end', $data) ? $data['slot_end'] : $appointment->slot_end;
        $branchId = $data['branch_id'] ?? $appointment->branch_id;

        if ($doctorUserId !== null && $slotStart && $slotEnd) {
            $slots->assertBookable(
                hospitalId: $context->hospitalId(),
                doctorUserId: (int) $doctorUserId,
                date: (string) $appointmentDate,
                slotStart: substr((string) $slotStart, 0, 5),
                slotEnd: substr((string) $slotEnd, 0, 5),
                visitType: $appointment->visit_type ?? 'first_visit',
                branchId: $branchId !== null ? (int) $branchId : null,
                ignoreAppointmentId: $appointment->id,
            );
        }

        $appointment->update([
            'branch_id' => $branchId,
            'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : $appointment->department_id,
            'doctor_user_id' => $doctorUserId,
            'appointment_date' => $appointmentDate,
            'slot_start' => $slotStart,
            'slot_end' => $slotEnd,
            'status' => $data['status'] ?? $appointment->status,
            'reason' => array_key_exists('reason', $data) ? $data['reason'] : $appointment->reason,
            'updated_by' => $request->user()?->id,
        ]);

        return ApiResponse::success(
            request: $request,
            data: new AppointmentResource($appointment->refresh()->load(['patient', 'doctor', 'department'])),
            message: 'Appointment updated',
        );
    }

    public function cancel(AppointmentCancelRequest $request, TenantContext $context, Appointment $appointment, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($appointment->hospital_id === $context->hospitalId(), 404);
        abort_unless(in_array($appointment->status, self::RESCHEDULABLE_STATUSES, true), 422, 'Appointment cannot be cancelled from its current status.');

        $previousStatus = $appointment->status;

        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->validated('cancellation_reason'),
            'updated_by' => $request->user()?->id,
        ]);

        $auditLogger->record(
            request: $request,
            module: 'appointments',
            event: 'appointment.cancelled',
            auditable: $appointment,
            old: ['status' => $previousStatus],
            new: ['status' => 'cancelled', 'cancellation_reason' => $appointment->cancellation_reason],
        );

        return ApiResponse::success(
            request: $request,
            data: new AppointmentResource($appointment->refresh()),
            message: 'Appointment cancelled',
        );
    }

    public function checkIn(Request $request, TenantContext $context, Appointment $appointment, TokenGenerator $tokenGenerator, AuditLogger $auditLogger): JsonResponse
    {
        abort_unless($appointment->hospital_id === $context->hospitalId(), 404);
        abort_if(in_array($appointment->status, self::CHECK_IN_BLOCKED_STATUSES, true), 422, 'Appointment cannot be checked in from its current status.');

        $queue = DB::transaction(function () use ($appointment, $context, $tokenGenerator, $request): OpdQueue {
            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            abort_if(in_array($locked->status, self::CHECK_IN_BLOCKED_STATUSES, true), 422, 'Appointment cannot be checked in from its current status.');

            $existingQueue = OpdQueue::query()->where('appointment_id', $locked->id)->first();

            if ($existingQueue !== null) {
                return $existingQueue;
            }

            $branchId = $locked->branch_id ?? $context->branchId();
            $tokenPrefix = $locked->department?->code;

            $token = $tokenGenerator->nextToken(
                hospitalId: $context->hospitalId(),
                branchId: $branchId,
                queueDate: $locked->appointment_date->toDateString(),
                departmentId: $locked->department_id,
                doctorUserId: $locked->doctor_user_id,
                tokenPrefix: $tokenPrefix,
            );

            $queue = OpdQueue::create([
                'hospital_id' => $context->hospitalId(),
                'branch_id' => $branchId,
                'appointment_id' => $locked->id,
                'patient_id' => $locked->patient_id,
                'department_id' => $locked->department_id,
                'doctor_user_id' => $locked->doctor_user_id,
                'queue_date' => $locked->appointment_date,
                'token_number' => $token['token_number'],
                'token_prefix' => $token['token_prefix'],
                'token_code' => $token['token_code'],
                'status' => 'waiting',
                'created_by' => $request->user()?->id,
            ]);

            $locked->update([
                'status' => 'checked_in',
                'checked_in_at' => now(),
                'updated_by' => $request->user()?->id,
            ]);

            return $queue;
        });

        $auditLogger->record(
            request: $request,
            module: 'appointments',
            event: 'appointment.checked_in',
            auditable: $appointment,
            new: ['status' => 'checked_in', 'token_code' => $queue->token_code],
        );

        return ApiResponse::success(
            request: $request,
            data: new AppointmentResource($appointment->refresh()->load(['patient', 'doctor', 'department'])),
            message: 'Patient checked in and token generated',
        );
    }
}
