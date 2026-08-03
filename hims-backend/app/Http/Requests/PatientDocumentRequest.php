<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:191'],
            'file_path' => ['nullable', 'string', 'max:2000'],
            'mime_type' => ['nullable', 'string', 'max:100'],
            'file_size' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
