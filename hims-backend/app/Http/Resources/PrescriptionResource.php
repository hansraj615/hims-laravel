<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_id' => $this->encounter_id,
            'prescription_number' => $this->prescription_number,
            'status' => $this->status,
            'prescribed_at' => $this->prescribed_at?->toISOString(),
            'items' => $this->whenLoaded('items', fn () => PrescriptionItemResource::collection($this->items)),
        ];
    }
}
