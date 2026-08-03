<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionClearanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_id' => $this->admission_id,
            'clearance_type' => $this->clearance_type,
            'status' => $this->status,
            'notes' => $this->notes,
            'cleared_at' => $this->cleared_at?->toISOString(),
            'cleared_by' => $this->whenLoaded('clearedBy', fn () => $this->clearedBy === null ? null : [
                'id' => $this->clearedBy->id,
                'name' => $this->clearedBy->name,
            ]),
        ];
    }
}
