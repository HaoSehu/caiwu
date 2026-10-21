<?php

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Support\AccountIdentifier;
use Illuminate\Validation\Rule;

class StoreUserRequest extends AdminFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => AccountIdentifier::normalizeOptionalPhone((string) $this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && $value !== '' && AccountIdentifier::detectType((string) $value) !== 'phone') {
                        $fail('请输入正确的手机号');
                    }
                },
                Rule::unique('users', 'phone'),
            ],
            'status' => ['nullable', 'in:0,1'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            // 初始余额走内核入账：必须是非负数，且不接受超过 decimal(12,2) 的金额
            'balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ];

        $rules['phone'][0] = 'required';

        return array_merge($rules, $this->allPaginationRules());
    }

    public function payload(): array
    {
        return $this->safe()->only([
            'email',
            'password',
            'nickname',
            'phone',
            'status',
            'credit_limit',
            'balance',
        ]);
    }
}
