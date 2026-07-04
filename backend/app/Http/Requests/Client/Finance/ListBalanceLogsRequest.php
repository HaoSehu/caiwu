<?php

namespace App\Http\Requests\Client\Finance;

use App\Http\Requests\Client\Common\ClientFormRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;

class ListBalanceLogsRequest extends ClientFormRequest
{
    use HasDateRangeFilter;

    public function rules(): array
    {
        return array_merge($this->paginationRules(200), [
            'event_type' => ['nullable', 'in:recharge,consume,refund,adjust,admin_deduct,manual_recharge,manual_deduction,invoice_payment,invoice_refund,system_adjustment,referral_withdraw_approved,referral_credit_cash'],
            'type' => ['nullable', 'in:recharge,consume,refund,adjust,admin_deduct,manual_recharge,manual_deduction,invoice_payment,invoice_refund,system_adjustment,referral_withdraw_approved,referral_credit_cash'],
            ...$this->dateRangeRules(),
        ]);
    }

    public function filters(): array
    {
        $filters = $this->safe()->only([
            'event_type',
            'type',
            'start_date',
            'end_date',
        ]);

        if (empty($filters['event_type']) && ! empty($filters['type'])) {
            $filters['event_type'] = $filters['type'];
        }

        unset($filters['type']);

        return $filters;
    }
}
