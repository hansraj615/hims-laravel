<?php

namespace App\Http\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        $context = app(TenantContext::class);

        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'encounter_id' => [
                'nullable',
                'integer',
                Rule::exists('encounters', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'invoice_type' => ['nullable', 'string', 'max:40'],
            'payer_type' => ['nullable', Rule::in(['self', 'insurance', 'tpa', 'corporate', 'government'])],
            'scheme_type' => ['nullable', 'string', 'max:80'],
            'tpa_name' => ['nullable', 'string', 'max:191'],
            'claim_reference' => ['nullable', 'string', 'max:191'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'items.*.description' => ['nullable', 'string', 'max:191'],
            'items.*.hsn_sac_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.cgst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.sgst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.igst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.is_tax_exempt' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                if (empty($item['service_id']) && empty($item['description'])) {
                    $validator->errors()->add("items.{$index}.description", 'Each item requires a service_id or a manual description.');
                }
            }
        });
    }
}
