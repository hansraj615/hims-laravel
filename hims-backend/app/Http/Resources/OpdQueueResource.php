<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpdQueueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'branch_id' => $this->branch_id,
            'appointment_id' => $this->appointment_id,
            'queue_date' => $this->queue_date?->toDateString(),
            'token_number' => $this->token_number,
            'token_prefix' => $this->token_prefix,
            'token_code' => $this->token_code,
            'status' => $this->status,
            'vitals' => $this->vitals,
            'has_vitals' => filled($this->vitals),
            'vitals_recorded_at' => $this->vitals_recorded_at?->toISOString(),
            'vitals_recorded_by' => $this->vitals_recorded_by,
            'called_at' => $this->called_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
