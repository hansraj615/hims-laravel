<?php

namespace App\Http\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultationStartRequest extends FormRequest
{
    public function rules(): array
    {
        $context = app(TenantContext::class);

        return [
            'opd_queue_id' => [
                'nullable',
                'integer',
                Rule::exists('opd_queues', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'patient_id' => [
                'nullable',
                'integer',
                Rule::exists('patients', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'doctor_user_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator): void {
            $hasQueue = filled($this->input('opd_queue_id'));
            $hasPatientAndAppointment = filled($this->input('patient_id')) && filled($this->input('appointment_id'));

            if (! $hasQueue && ! $hasPatientAndAppointment) {
                $validator->errors()->add(
                    'opd_queue_id',
                    'Provide either opd_queue_id or both patient_id and appointment_id to start a consultation.',
                );
            }
        });
    }
}
