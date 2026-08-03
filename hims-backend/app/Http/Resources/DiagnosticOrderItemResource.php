<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiagnosticOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'diagnostic_order_id' => $this->diagnostic_order_id,
            'service_id' => $this->service_id,
            'service_code' => $this->service_code,
            'service_name' => $this->service_name,
            'category' => $this->category,
            'quantity' => $this->quantity,
            'unit_rate' => $this->unit_rate,
            'status' => $this->status,
        ];
    }
}
