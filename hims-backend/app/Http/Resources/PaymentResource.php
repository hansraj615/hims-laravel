<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'patient_id' => $this->patient_id,
            'receipt_number' => $this->receipt_number,
            'payment_type' => $this->payment_type,
            'payment_mode' => $this->payment_mode,
            'amount' => $this->amount,
            'status' => $this->status,
            'reference_number' => $this->reference_number,
            'bank_name' => $this->bank_name,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
