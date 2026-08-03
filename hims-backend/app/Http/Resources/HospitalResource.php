<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HospitalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'code' => $this->code,
            'registration_number' => $this->registration_number,
            'gstin' => $this->gstin,
            'phone' => $this->phone,
            'status' => $this->status,
        ];
    }
}
