<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vitals' => ['nullable', 'array'],
            'chief_complaints' => ['nullable', 'array'],
            'clinical_history' => ['nullable', 'array'],
            'examination' => ['nullable', 'array'],
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*.code' => ['nullable', 'string', 'max:50'],
            'diagnoses.*.system' => ['nullable', 'string', 'max:191'],
            'diagnoses.*.display' => ['required_with:diagnoses', 'string', 'max:191'],
            'diagnoses.*.type' => ['nullable', 'string', 'max:50'],
            'care_plan' => ['nullable', 'array'],
            'follow_up' => ['nullable', 'array'],
            'prescription_items' => ['nullable', 'array'],
            'prescription_items.*.medicine_name' => ['required_with:prescription_items', 'string', 'max:191'],
            'prescription_items.*.generic_name' => ['nullable', 'string', 'max:191'],
            'prescription_items.*.formulation' => ['nullable', 'string', 'max:191'],
            'prescription_items.*.strength' => ['nullable', 'string', 'max:191'],
            'prescription_items.*.route' => ['nullable', 'string', 'max:40'],
            'prescription_items.*.frequency' => ['nullable', 'string', 'max:80'],
            'prescription_items.*.duration' => ['nullable', 'string', 'max:80'],
            'prescription_items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'prescription_items.*.instructions' => ['nullable', 'string', 'max:1000'],
            'prescription_items.*.is_schedule_h' => ['nullable', 'boolean'],
            'prescription_items.*.is_schedule_h1' => ['nullable', 'boolean'],
        ];
    }
}
