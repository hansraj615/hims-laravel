<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'branch_id' => $this->branch_id,
            'patient_id' => $this->patient_id,
            'department_id' => $this->department_id,
            'doctor_user_id' => $this->doctor_user_id,
            'appointment_number' => $this->appointment_number,
            'appointment_date' => $this->appointment_date?->toDateString(),
            'slot_start' => $this->slot_start,
            'slot_end' => $this->slot_end,
            'visit_type' => $this->visit_type,
            'source' => $this->source,
            'priority' => $this->priority,
            'status' => $this->status,
            'fee_amount' => $this->fee_amount,
            'payment_status' => $this->payment_status,
            'reason' => $this->reason,
            'cancellation_reason' => $this->cancellation_reason,
            'checked_in_at' => $this->checked_in_at?->toISOString(),
            'patient' => $this->whenLoaded('patient', fn () => $this->patient === null ? null : [
                'id' => $this->patient->id,
                'uhid' => $this->patient->uhid,
                'name' => $this->patient->full_name,
                'mobile' => $this->patient->mobile,
            ]),
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor === null ? null : [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ]),
            'department' => $this->whenLoaded('department', fn () => $this->department === null ? null : [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'code' => $this->department->code,
            ]),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
