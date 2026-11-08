<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\CouponCampaign;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class ListCouponCampaignsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            ...$this->paginationRules(presence: 'sometimes'),
            'per_page' => ['prohibited'],
            'pageSize' => ['prohibited'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:0,1'],
        ];
    }
}
