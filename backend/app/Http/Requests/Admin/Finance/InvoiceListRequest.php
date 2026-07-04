<?php

namespace App\Http\Requests\Admin\Finance;

use App\Constants\InvoiceType;
use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InvoiceListRequest extends AdminFormRequest
{
    use HasDateRangeFilter;

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:80'],
            'invoice_no' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::in([
                InvoiceType::NEW_PURCHASE,
                'normal',
                InvoiceType::RENEW,
                InvoiceType::RECHARGE,
                InvoiceType::UPGRADE,
                InvoiceType::DEDUCTION,
                InvoiceType::REFERRAL_CREDIT,
                InvoiceType::MANUAL,
            ])],
            'product_id' => ['nullable', 'integer', 'min:1'],
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
