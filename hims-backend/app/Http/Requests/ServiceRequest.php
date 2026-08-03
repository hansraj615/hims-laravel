<?php

namespace App\Http\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function rules(): array
    {
        $context = app(TenantContext::class);
        $serviceId = $this->route('service')?->id;

        return [
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'name' => ['required', 'string', 'max:191'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('services', 'code')->where('hospital_id', $context->hospitalId())->ignore($serviceId),
            ],
            'service_type' => ['required', 'string', 'max:50'],
            'category' => ['required', Rule::in(['opd', 'ipd', 'pathology', 'radiology', 'procedure', 'consultant_fee'])],
            'hsn_sac_code' => ['nullable', 'string', 'max:20'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'cgst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sgst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'igst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_tax_exempt' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
