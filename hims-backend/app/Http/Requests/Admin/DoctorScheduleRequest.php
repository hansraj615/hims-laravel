<?php

namespace App\Http\Requests\Admin;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoctorScheduleRequest extends FormRequest
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
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
