<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IpdNursingNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_id' => $this->admission_id,
            'recorded_at' => $this->recorded_at?->toISOString(),
            'vitals' => $this->vitals,
            'notes' => $this->notes,
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy === null ? null : [
                'id' => $this->recordedBy->id,
                'name' => $this->recordedBy->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
