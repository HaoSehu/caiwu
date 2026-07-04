<?php

namespace App\Http\Requests\Admin\Verification;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use Illuminate\Validation\Validator;

class ListVerificationsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'keyword' => ['nullable', 'string', 'max:100'],
            'is_verified' => ['nullable', 'in:0,1'],
            'verification_status' => ['nullable', 'in:0,1,2,3,5'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'keyword',
            'is_verified',
            'verification_status',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->rejectPrivacyFilters($validator, [], [
            'keyword' => '邮箱、手机号、证件号等隐私关键词',
        ]));
    }
}
