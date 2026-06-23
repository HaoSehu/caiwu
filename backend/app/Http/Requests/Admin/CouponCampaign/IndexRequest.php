<?php

namespace App\Http\Requests\Admin\CouponCampaign;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class IndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:0,1'],
        ];
    }
}
