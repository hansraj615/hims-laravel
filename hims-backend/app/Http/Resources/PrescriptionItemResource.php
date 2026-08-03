<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medicine_name' => $this->medicine_name,
            'generic_name' => $this->generic_name,
            'formulation' => $this->formulation,
            'strength' => $this->strength,
            'route' => $this->route,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'quantity' => $this->quantity,
            'instructions' => $this->instructions,
            'is_schedule_h' => $this->is_schedule_h,
            'is_schedule_h1' => $this->is_schedule_h1,
        ];
    }
}
