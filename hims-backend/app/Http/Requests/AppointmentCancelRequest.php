<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppointmentCancelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
