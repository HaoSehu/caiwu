<?php

namespace App\Http\Requests\Client\Auth;

use App\Http\Requests\Client\Common\ClientFormRequest;
use App\Support\AccountIdentifier;
use Closure;

class SendPhoneCodeRequest extends ClientFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => AccountIdentifier::normalizePhone((string) $this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) {
                if (AccountIdentifier::detectType((string) $value) !== 'phone') {
                    $fail('请输入正确的手机号');
                }
            }],
        ];
    }
}
