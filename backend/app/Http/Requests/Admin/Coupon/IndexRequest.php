<?php

namespace App\Http\Requests\Admin\Coupon;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:0,1,expired'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'distribution_type' => ['nullable', Rule::in(['public', 'private'])],
            'discount_scope' => ['nullable', Rule::in(['first_month', 'recurring', 'renew'])],
        ];
    }
}
