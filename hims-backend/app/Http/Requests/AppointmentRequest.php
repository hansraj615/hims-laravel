<?php

namespace App\Http\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRequest extends FormRequest
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
            'appointment_date' => ['required', 'date'],
            'slot_start' => ['nullable', 'date_format:H:i'],
            'slot_end' => ['nullable', 'date_format:H:i', 'after:slot_start'],
            'visit_type' => ['nullable', Rule::in(['first_visit', 'follow_up', 'emergency'])],
            'source' => ['nullable', Rule::in(['walk_in', 'phone', 'online', 'referral'])],
            'priority' => ['nullable', Rule::in(['normal', 'urgent', 'vip'])],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
