<?php

namespace App\Http\Requests;

use App\Domain\IPD\Models\Admission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdmissionDischargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(Admission::EXIT_OUTCOMES)],
            'discharge_summary' => ['required', 'string', 'max:10000'],
            'death_at' => ['nullable', 'required_if:outcome,death', 'date'],
            'create_invoice' => ['nullable', 'boolean'],
        ];
    }
}
