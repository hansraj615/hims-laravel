<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiagnosticOrderResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'result_summary' => ['required', 'string', 'max:5000'],
            'result_payload' => ['nullable', 'array'],
        ];
    }
}
