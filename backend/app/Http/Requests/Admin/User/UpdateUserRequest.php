<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\User;
use App\Support\AccountIdentifier;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends AdminFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => AccountIdentifier::normalizeOptionalPhone((string) $this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $ignoreUserId = $user instanceof User ? (int) $user->id : (is_numeric($user) ? (int) $user : null);

        return [
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
                Rule::unique('users', 'phone')->ignore($ignoreUserId),
            ],
            'password' => ['nullable', 'string', 'min:6'],
            'status' => ['nullable', 'in:0,1'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only([
            'nickname',
            'phone',
            'password',
            'status',
            'credit_limit',
        ]);
    }
}
