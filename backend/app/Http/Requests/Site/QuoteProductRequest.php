<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class QuoteProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_cycle' => ['required', 'string', 'max:30'],
            'config' => ['nullable', 'array'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'user_coupon_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
