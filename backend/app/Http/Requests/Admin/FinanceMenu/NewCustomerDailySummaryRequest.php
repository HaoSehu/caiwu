<?php

namespace App\Http\Requests\Admin\FinanceMenu;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class NewCustomerDailySummaryRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m', 'required_without:start_date'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
        ];
    }
}
