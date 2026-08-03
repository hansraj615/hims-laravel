<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EncounterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'branch_id' => $this->branch_id,
            'patient_id' => $this->patient_id,
            'appointment_id' => $this->appointment_id,
            'opd_queue_id' => $this->opd_queue_id,
            'department_id' => $this->department_id,
            'doctor_user_id' => $this->doctor_user_id,
            'encounter_number' => $this->encounter_number,
            'encounter_type' => $this->encounter_type,
            'status' => $this->status,
            'vitals' => $this->vitals,
            'chief_complaints' => $this->chief_complaints,
            'clinical_history' => $this->clinical_history,
            'examination' => $this->examination,
            'diagnoses' => $this->diagnoses,
            'care_plan' => $this->care_plan,
            'follow_up' => $this->follow_up,
            'fhir_payload' => $this->fhir_payload,
            'fhir_version' => $this->fhir_version,
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
            'prescriptions' => $this->whenLoaded('prescriptions', fn () => PrescriptionResource::collection($this->prescriptions)),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
