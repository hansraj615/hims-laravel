<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OtpVerifyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'min:3', 'max:10'],
        ];
    }
}
