<?php

namespace App\Http\Requests;

use App\Domain\Diagnostics\Models\DiagnosticOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiagnosticOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'category' => ['required', Rule::in(DiagnosticOrder::CATEGORIES)],
            'priority' => ['nullable', Rule::in(['routine', 'urgent'])],
            'clinical_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
