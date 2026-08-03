<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'branch_id' => $this->branch_id,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'invoice_number' => $this->invoice_number,
            'invoice_type' => $this->invoice_type,
            'payer_type' => $this->payer_type,
            'scheme_type' => $this->scheme_type,
            'tpa_name' => $this->tpa_name,
            'claim_reference' => $this->claim_reference,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'taxable_total' => $this->taxable_total,
            'cgst_total' => $this->cgst_total,
            'sgst_total' => $this->sgst_total,
            'igst_total' => $this->igst_total,
            'round_off' => $this->round_off,
            'grand_total' => $this->grand_total,
            'paid_total' => $this->paid_total,
            'balance_total' => $this->balance_total,
            'billed_at' => $this->billed_at?->toISOString(),
            'patient' => $this->whenLoaded('patient', fn () => $this->patient === null ? null : [
                'id' => $this->patient->id,
                'uhid' => $this->patient->uhid,
                'name' => $this->patient->full_name,
                'mobile' => $this->patient->mobile,
            ]),
            'items' => $this->whenLoaded('items', fn () => InvoiceItemResource::collection($this->items)),
            'payments' => $this->whenLoaded('payments', fn () => PaymentResource::collection($this->payments)),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
