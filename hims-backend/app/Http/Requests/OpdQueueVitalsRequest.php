<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpdQueueVitalsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vitals' => ['nullable', 'array'],
            'vitals.temperature_c' => ['nullable', 'numeric', 'between:30,45'],
            'vitals.pulse_bpm' => ['nullable', 'integer', 'between:20,250'],
            'vitals.respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'vitals.bp_systolic' => ['nullable', 'integer', 'between:40,300'],
            'vitals.bp_diastolic' => ['nullable', 'integer', 'between:20,200'],
            'vitals.spo2_percent' => ['nullable', 'numeric', 'between:50,100'],
            'vitals.height_cm' => ['nullable', 'numeric', 'between:20,250'],
            'vitals.weight_kg' => ['nullable', 'numeric', 'between:1,400'],
        ];
    }
}
