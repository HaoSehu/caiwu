<?php

namespace App\Http\Requests\Admin\ReferralWithdrawal;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use Illuminate\Validation\Validator;

class IndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->rejectPrivacyFilters($validator, [], [
            'keyword' => '邮箱、手机号等隐私关键词',
        ]));
    }
}
