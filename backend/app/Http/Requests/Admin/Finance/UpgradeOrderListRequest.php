<?php

namespace App\Http\Requests\Admin\Finance;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use Illuminate\Validation\Validator;

class UpgradeOrderListRequest extends AdminFormRequest
{
    use HasDateRangeFilter;

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'integer'],
            'upgrade_kind' => ['nullable', 'string', 'max:80'],
            ...$this->dateRangeRules(),
            ...$this->paginationRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateDateRange($validator);
            $this->rejectPrivacyFilters($validator, [], [
                'keyword' => '邮箱、手机号等隐私关键词',
            ]);
        });
    }
}
