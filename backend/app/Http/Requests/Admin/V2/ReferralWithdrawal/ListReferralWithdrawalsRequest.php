<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\ReferralWithdrawal;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Validator;

class ListReferralWithdrawalsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'keyword' => ['nullable', 'string', 'max:100'],
            // 3 = 已打款、4 = 打款失败：打款确认/失败回退通道打通后两态均可达，
            // 筛选必须能选到，否则财务无法核对已出款与已退回的记录。
            'status' => ['nullable', 'integer', 'in:0,1,2,3,4'],
            'per_page' => ['prohibited'],
            'pageSize' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->rejectPrivacyFilters($validator, [], [
            'keyword' => '邮箱、手机号等隐私关键词',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->validated();
    }

    public function pageSize(): int
    {
        return $this->perPage(20, 100);
    }
}
