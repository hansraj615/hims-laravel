<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
            'admitting_doctor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'admitted_at' => ['nullable', 'date'],
            'provisional_diagnosis' => ['nullable', 'string', 'max:500'],
            'attendant_name' => ['nullable', 'string', 'max:150'],
            'attendant_mobile' => ['nullable', 'string', 'max:30'],
            'attendant_relation' => ['nullable', 'string', 'max:60'],
        ];
    }
}
