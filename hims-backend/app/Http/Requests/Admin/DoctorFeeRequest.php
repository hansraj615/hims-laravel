<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoctorFeeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'visit_type' => ['required', Rule::in(['first_visit', 'follow_up', 'emergency'])],
            'fee_amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
