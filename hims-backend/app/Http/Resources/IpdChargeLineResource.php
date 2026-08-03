<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IpdChargeLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_id' => $this->admission_id,
            'service_id' => $this->service_id,
            'charge_date' => $this->charge_date?->toDateString(),
            'description' => $this->description,
            'source' => $this->source,
            'quantity' => $this->quantity,
            'unit_rate' => $this->unit_rate,
            'amount' => $this->amount,
            'status' => $this->status,
            'invoice_id' => $this->invoice_id,
        ];
    }
}
