<?php

namespace App\Domain\Appointments\Services;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\DoctorFeeMaster;
use App\Domain\Appointments\Models\DoctorLeave;
use App\Domain\Appointments\Models\DoctorSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SlotAvailabilityService
{
    private const BLOCKING_STATUSES = ['booked', 'confirmed', 'checked_in', 'completed'];

    public function doctorUsesSchedules(int $hospitalId, int $doctorUserId): bool
    {
        return DoctorSchedule::query()
            ->forHospital($hospitalId)
            ->where('doctor_user_id', $doctorUserId)
            ->active()
            ->exists();
    }

    public function resolveFee(int $hospitalId, int $doctorUserId, string $visitType, ?string $onDate = null): ?string
    {
        $date = $onDate ?? now()->toDateString();

        $fee = DoctorFeeMaster::query()
            ->forHospital($hospitalId)
            ->where('doctor_user_id', $doctorUserId)
            ->where('visit_type', $visitType)
            ->active()
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->first();

        return $fee?->fee_amount;
    }

    /**
     * @return array{slots: list<array{slot_start: string, slot_end: string, available: bool}>, on_leave: bool, leave_reason: ?string, fee_amount: ?string}
     */
    public function availability(
        int $hospitalId,
        int $doctorUserId,
        string $date,
        string $visitType = 'first_visit',
        ?int $branchId = null,
        ?int $ignoreAppointmentId = null,
    ): array {
        $carbon = Carbon::parse($date)->startOfDay();
        $leave = $this->activeLeaveOn($hospitalId, $doctorUserId, $date);

        if ($leave !== null) {
            return [
                'slots' => [],
                'on_leave' => true,
                'leave_reason' => $leave->reason,
                'fee_amount' => $this->resolveFee($hospitalId, $doctorUserId, $visitType, $date),
            ];
        }

        $schedules = DoctorSchedule::query()
            ->forHospital($hospitalId)
            ->where('doctor_user_id', $doctorUserId)
            ->where('day_of_week', $carbon->dayOfWeek)
            ->active()
            ->when($branchId !== null, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereNull('branch_id')
                ->orWhere('branch_id', $branchId)))
            ->orderBy('start_time')
            ->get();

        $booked = $this->bookedSlots($hospitalId, $doctorUserId, $date, $ignoreAppointmentId);
        $slots = [];

        foreach ($schedules as $schedule) {
            foreach ($this->generateWindows($schedule) as $window) {
                $slots[] = [
                    'slot_start' => $window['start'],
                    'slot_end' => $window['end'],
                    'available' => ! $this->isTaken($booked, $window['start'], $window['end']),
                ];
            }
        }

        return [
            'slots' => $slots,
            'on_leave' => false,
            'leave_reason' => null,
            'fee_amount' => $this->resolveFee($hospitalId, $doctorUserId, $visitType, $date),
        ];
    }

    public function assertBookable(
        int $hospitalId,
        int $doctorUserId,
        string $date,
        string $slotStart,
        string $slotEnd,
        string $visitType = 'first_visit',
        ?int $branchId = null,
        ?int $ignoreAppointmentId = null,
    ): void {
        if (! $this->doctorUsesSchedules($hospitalId, $doctorUserId)) {
            return;
        }

        $availability = $this->availability(
            hospitalId: $hospitalId,
            doctorUserId: $doctorUserId,
            date: $date,
            visitType: $visitType,
            branchId: $branchId,
            ignoreAppointmentId: $ignoreAppointmentId,
        );

        if ($availability['on_leave']) {
            throw ValidationException::withMessages([
                'appointment_date' => ['Doctor is on leave for the selected date.'.($availability['leave_reason'] ? ' Reason: '.$availability['leave_reason'] : '')],
            ]);
        }

        $slotStart = substr($slotStart, 0, 5);
        $slotEnd = substr($slotEnd, 0, 5);

        $match = collect($availability['slots'])->first(
            fn (array $slot) => $slot['slot_start'] === $slotStart && $slot['slot_end'] === $slotEnd,
        );

        if ($match === null) {
            throw ValidationException::withMessages([
                'slot_start' => ['Selected slot is outside the doctor schedule for this date.'],
            ]);
        }

        if (! $match['available']) {
            throw ValidationException::withMessages([
                'slot_start' => ['Selected slot is already booked.'],
            ]);
        }
    }

    private function activeLeaveOn(int $hospitalId, int $doctorUserId, string $date): ?DoctorLeave
    {
        return DoctorLeave::query()
            ->forHospital($hospitalId)
            ->where('doctor_user_id', $doctorUserId)
            ->active()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /**
     * @return Collection<int, array{start: string, end: string}>
     */
    private function bookedSlots(int $hospitalId, int $doctorUserId, string $date, ?int $ignoreAppointmentId): Collection
    {
        return Appointment::query()
            ->forHospital($hospitalId)
            ->where('doctor_user_id', $doctorUserId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->when($ignoreAppointmentId !== null, fn ($query) => $query->where('id', '!=', $ignoreAppointmentId))
            ->whereNotNull('slot_start')
            ->whereNotNull('slot_end')
            ->get(['slot_start', 'slot_end'])
            ->map(fn (Appointment $appointment) => [
                'start' => substr((string) $appointment->slot_start, 0, 5),
                'end' => substr((string) $appointment->slot_end, 0, 5),
            ]);
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    private function generateWindows(DoctorSchedule $schedule): array
    {
        $duration = max(5, (int) $schedule->slot_duration_minutes);
        $cursor = Carbon::createFromFormat('H:i:s', strlen((string) $schedule->start_time) === 5
            ? $schedule->start_time.':00'
            : (string) $schedule->start_time);
        $end = Carbon::createFromFormat('H:i:s', strlen((string) $schedule->end_time) === 5
            ? $schedule->end_time.':00'
            : (string) $schedule->end_time);

        $windows = [];

        while ($cursor->copy()->addMinutes($duration)->lte($end)) {
            $slotEnd = $cursor->copy()->addMinutes($duration);
            $windows[] = [
                'start' => $cursor->format('H:i'),
                'end' => $slotEnd->format('H:i'),
            ];
            $cursor = $slotEnd;
        }

        return $windows;
    }

    /**
     * @param  Collection<int, array{start: string, end: string}>  $booked
     */
    private function isTaken(Collection $booked, string $start, string $end): bool
    {
        return $booked->contains(function (array $slot) use ($start, $end): bool {
            return $slot['start'] < $end && $slot['end'] > $start;
        });
    }
}
