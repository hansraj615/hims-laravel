<?php

namespace App\Http\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRescheduleRequest extends FormRequest
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
            'doctor_user_id' => [
                'nullable',
                'integer',
                Rule::exists('user_hospital_branch_assignments', 'user_id')
                    ->where('hospital_id', $context->hospitalId())
                    ->where('status', 'active'),
            ],
            'appointment_date' => ['nullable', 'date'],
            'slot_start' => ['nullable', 'date_format:H:i'],
            'slot_end' => ['nullable', 'date_format:H:i', 'after:slot_start'],
            'status' => ['nullable', Rule::in(['booked', 'confirmed'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
