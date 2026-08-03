<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'department_id' => $this->department_id,
            'name' => $this->name,
            'code' => $this->code,
            'service_type' => $this->service_type,
            'category' => $this->category,
            'hsn_sac_code' => $this->hsn_sac_code,
            'base_rate' => $this->base_rate,
            'cgst_rate' => $this->cgst_rate,
            'sgst_rate' => $this->sgst_rate,
            'igst_rate' => $this->igst_rate,
            'is_tax_exempt' => $this->is_tax_exempt,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
