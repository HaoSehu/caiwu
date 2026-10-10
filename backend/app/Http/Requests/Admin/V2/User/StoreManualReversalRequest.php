<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class StoreManualReversalRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            // 冲抵金额可省略（默认全冲原单入账额），填写时必须为正、最多两位小数，
            // 且不得超过原单入账额（服务层二次校验，防绕过前端）。
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/'],
            'remark' => ['required', 'string', 'min:2', 'max:200'],
            'per_page' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.regex' => '金额最多保留两位小数',
            'remark.required' => '请填写红字冲抵原因',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->only([
            'amount',
            'remark',
        ]);
    }
}
