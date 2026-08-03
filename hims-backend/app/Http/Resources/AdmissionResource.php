<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'branch_id' => $this->branch_id,
            'admission_number' => $this->admission_number,
            'patient_id' => $this->patient_id,
            'admitting_doctor_user_id' => $this->admitting_doctor_user_id,
            'department_id' => $this->department_id,
            'ward_id' => $this->ward_id,
            'bed_id' => $this->bed_id,
            'admitted_at' => $this->admitted_at?->toISOString(),
            'provisional_diagnosis' => $this->provisional_diagnosis,
            'attendant_name' => $this->attendant_name,
            'attendant_mobile' => $this->attendant_mobile,
            'attendant_relation' => $this->attendant_relation,
            'status' => $this->status,
            'discharge_outcome' => $this->discharge_outcome,
            'discharged_at' => $this->discharged_at?->toISOString(),
            'discharge_summary' => $this->discharge_summary,
            'discharge_package' => $this->discharge_package,
            'death_at' => $this->death_at?->toISOString(),
            'invoice_id' => $this->invoice_id,
            'discharge_document_id' => $this->discharge_document_id,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient === null ? null : [
                'id' => $this->patient->id,
                'uhid' => $this->patient->uhid,
                'name' => $this->patient->full_name,
                'mobile' => $this->patient->mobile,
            ]),
            'ward' => new WardResource($this->whenLoaded('ward')),
            'bed' => $this->whenLoaded('bed', fn () => $this->bed === null ? null : [
                'id' => $this->bed->id,
                'bed_number' => $this->bed->bed_number,
                'bed_type' => $this->bed->bed_type,
                'status' => $this->bed->status,
            ]),
            'admitting_doctor' => $this->whenLoaded('admittingDoctor', fn () => $this->admittingDoctor === null ? null : [
                'id' => $this->admittingDoctor->id,
                'name' => $this->admittingDoctor->name,
            ]),
            'clearances' => AdmissionClearanceResource::collection($this->whenLoaded('clearances')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
