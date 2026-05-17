<?php

namespace App\Http\Requests\Admin\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Http\Requests\Admin\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class FinanceLedgerListRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(100), [
            'tab' => ['nullable', Rule::in(['all', 'invoices', 'balance', 'recharge', 'adjustment'])],
            'event_type' => ['nullable', Rule::in(FinanceLedgerEventType::allowedFilterValues())],
            'direction' => ['nullable', Rule::in([FinanceLedgerEventType::DIRECTION_IN, FinanceLedgerEventType::DIRECTION_OUT])],
            'status' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'invoice_no' => ['nullable', 'string', 'max:50'],
            'payment_no' => ['nullable', 'string', 'max:50'],
            'date_range' => ['nullable', 'array', 'size:2'],
            'date_range.0' => ['required_with:date_range', 'date'],
            'date_range.1' => ['required_with:date_range', 'date'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'tab',
            'event_type',
            'direction',
            'status',
            'user_id',
            'invoice_no',
            'payment_no',
            'date_range',
        ]);
    }
}
