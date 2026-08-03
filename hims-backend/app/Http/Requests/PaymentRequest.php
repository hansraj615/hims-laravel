<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'payment_mode' => ['required', Rule::in(['cash', 'upi', 'card', 'bank', 'cheque', 'mixed'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:191'],
            'bank_name' => ['nullable', 'string', 'max:191'],
        ];
    }
}
