<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Appointments\Models\DoctorSchedule */
class DoctorScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'branch_id' => $this->branch_id,
            'doctor_user_id' => $this->doctor_user_id,
            'department_id' => $this->department_id,
            'day_of_week' => $this->day_of_week,
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
            'slot_duration_minutes' => $this->slot_duration_minutes,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
