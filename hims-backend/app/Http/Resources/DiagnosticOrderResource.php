<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiagnosticOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'branch_id' => $this->branch_id,
            'order_number' => $this->order_number,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'appointment_id' => $this->appointment_id,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'clinical_notes' => $this->clinical_notes,
            'result_summary' => $this->result_summary,
            'result_payload' => $this->result_payload,
            'ordered_at' => $this->ordered_at?->toISOString(),
            'collected_at' => $this->collected_at?->toISOString(),
            'resulted_at' => $this->resulted_at?->toISOString(),
            'invoice_id' => $this->invoice_id,
            'patient_document_id' => $this->patient_document_id,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient === null ? null : [
                'id' => $this->patient->id,
                'uhid' => $this->patient->uhid,
                'name' => $this->patient->full_name,
                'mobile' => $this->patient->mobile,
            ]),
            'ordered_by_user' => $this->whenLoaded('orderedBy', fn () => $this->orderedBy === null ? null : [
                'id' => $this->orderedBy->id,
                'name' => $this->orderedBy->name,
            ]),
            'items' => DiagnosticOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
