<?php

namespace App\Http\Requests\Admin;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function rules(): array
    {
        $context = app(TenantContext::class);
        $departmentId = $this->route('department')?->id;

        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'name' => ['required', 'string', 'max:191'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('departments', 'code')
                    ->where('hospital_id', $context->hospitalId())
                    ->ignore($departmentId),
            ],
            'department_type' => ['required', Rule::in(['clinical', 'diagnostic', 'administrative', 'support'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
