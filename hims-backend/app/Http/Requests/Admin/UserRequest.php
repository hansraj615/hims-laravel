<?php

namespace App\Http\Requests\Admin;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function rules(): array
    {
        $context = app(TenantContext::class);
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'mobile' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'mobile')->ignore($userId),
            ],
            'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'min:8'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'assignment_type' => ['nullable', Rule::in(['staff', 'consultant', 'visiting', 'contract'])],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
