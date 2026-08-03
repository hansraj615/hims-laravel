<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdmissionTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
