<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'branch_id' => $this->branch_id,
            'ward_id' => $this->ward_id,
            'bed_number' => $this->bed_number,
            'bed_type' => $this->bed_type,
            'status' => $this->status,
            'current_admission_id' => $this->current_admission_id,
            'ward' => new WardResource($this->whenLoaded('ward')),
            'current_admission' => new AdmissionResource($this->whenLoaded('currentAdmission')),
        ];
    }
}
