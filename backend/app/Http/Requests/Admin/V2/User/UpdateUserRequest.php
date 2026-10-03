<?php

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Models\User;
use App\Support\AccountIdentifier;
use App\Support\AdminPrivacy;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends AdminFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $phone = trim((string) $this->input('phone'));

            // 无原始隐私权限的管理员拿到的是脱敏回显值（如 138****1234）。
            // 含 * 的值不参与格式/唯一性校验：与该用户当前手机号的脱敏形态一致时
            // 视为「未修改」直接剔除字段；不一致时保留原值，由 rules() 强校验拒绝，
            // 绝不能把脱敏串剥成残缺号码后覆盖入库。
            if (str_contains($phone, '*')) {
                if ($this->maskedPhoneMatchesCurrentUser($phone)) {
                    $this->offsetUnset('phone');
                }

                return;
            }

            $this->merge([
                'phone' => AccountIdentifier::normalizeOptionalPhone($phone),
            ]);
        }
    }

    /**
     * 脱敏手机号是否与当前用户手机号的脱敏形态一致（即「未修改」）。
     */
    private function maskedPhoneMatchesCurrentUser(string $maskedPhone): bool
    {
        $user = $this->route('user');
        $currentPhone = trim((string) ($user instanceof User ? $user->phone : ''));

        return $currentPhone !== '' && (new AdminPrivacy(false, true))->phone($currentPhone) === $maskedPhone;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $ignoreUserId = $user instanceof User ? (int) $user->id : (is_numeric($user) ? (int) $user : null);

        $rules = [
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
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['nullable', 'in:0,1'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ];

        $rules['phone'][0] = 'sometimes';
        array_splice($rules['phone'], 1, 0, 'required');

        return array_merge($rules, $this->allPaginationRules());
    }

    public function validatedPayload(): array
    {
        return $this->safe()->only([
            'nickname',
            'phone',
            'password',
            'status',
            'credit_limit',
            'admin_note',
        ]);
    }

    public function payload(): array
    {
        return $this->validatedPayload();
    }
}
