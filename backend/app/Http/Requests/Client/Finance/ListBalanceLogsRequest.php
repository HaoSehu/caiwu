<?php

namespace App\Http\Requests\Client\Finance;

use App\Http\Requests\Client\Common\ClientFormRequest;

class ListBalanceLogsRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(200), [
            'event_type' => ['nullable', 'in:recharge,consume,refund,adjust,admin_deduct,manual_recharge,manual_deduction,invoice_payment,invoice_refund,system_adjustment,referral_withdraw_approved,referral_credit_cash'],
            'type' => ['nullable', 'in:recharge,consume,refund,adjust,admin_deduct,manual_recharge,manual_deduction,invoice_payment,invoice_refund,system_adjustment,referral_withdraw_approved,referral_credit_cash'],
            'date_range' => ['nullable', 'array', 'size:2'],
            'date_range.0' => ['required_with:date_range', 'date'],
            'date_range.1' => ['required_with:date_range', 'date'],
        ]);
    }

    public function filters(): array
    {
        $filters = $this->safe()->only([
            'event_type',
            'type',
            'date_range',
        ]);

        if (empty($filters['event_type']) && ! empty($filters['type'])) {
            $filters['event_type'] = $filters['type'];
        }

        unset($filters['type']);

        return $filters;
    }
}
