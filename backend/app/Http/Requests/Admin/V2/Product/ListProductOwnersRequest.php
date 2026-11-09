<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Product;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Validator;

class ListProductOwnersRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'keyword' => ['nullable', 'string', 'max:100'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only(['keyword']);
    }

    public function withValidator(Validator $validator): void
    {
        // keyword 会透传 User::scopeSearch，可命中 email/phone；无原始隐私权限时
        // 禁止借关键词做邮箱/手机号检索，避免绕过列表脱敏反查用户。
        $validator->after(fn (Validator $validator) => $this->rejectPrivacyFilters($validator, [], [
            'keyword' => '邮箱、手机号、证件号等隐私关键词',
        ]));
    }
}
