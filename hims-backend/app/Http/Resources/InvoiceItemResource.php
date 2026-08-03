<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'description' => $this->description,
            'hsn_sac_code' => $this->hsn_sac_code,
            'quantity' => $this->quantity,
            'unit_rate' => $this->unit_rate,
            'gross_amount' => $this->gross_amount,
            'discount_amount' => $this->discount_amount,
            'taxable_amount' => $this->taxable_amount,
            'cgst_rate' => $this->cgst_rate,
            'sgst_rate' => $this->sgst_rate,
            'igst_rate' => $this->igst_rate,
            'cgst_amount' => $this->cgst_amount,
            'sgst_amount' => $this->sgst_amount,
            'igst_amount' => $this->igst_amount,
            'net_amount' => $this->net_amount,
            'status' => $this->status,
        ];
    }
}
