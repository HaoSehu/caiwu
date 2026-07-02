<?php

namespace App\Http\Requests\Admin\MemberLevel;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class SaveRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'code' => ['nullable', 'string', 'max:30'],
            'sales_amount_min' => ['required', 'numeric', 'min:0'],
            'sales_amount_max' => ['nullable', 'numeric', 'min:0'],
            'reward_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
