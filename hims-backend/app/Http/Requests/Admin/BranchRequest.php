<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function rules(): array
    {
        $hospitalId = app(\App\Support\Tenancy\TenantContext::class)->hospitalId();
        $branchId = $this->route('branch')?->id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('branches', 'code')
                    ->where('hospital_id', $hospitalId)
                    ->ignore($branchId),
            ],
            'facility_type' => ['required', Rule::in(['hospital', 'clinic', 'diagnostic_centre'])],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20'],
            'timezone' => ['required', 'string', 'max:64'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
