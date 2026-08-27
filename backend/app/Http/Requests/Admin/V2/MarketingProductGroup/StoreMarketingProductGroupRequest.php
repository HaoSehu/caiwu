<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\MarketingProductGroup;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class StoreMarketingProductGroupRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only([
            'name',
            'sort_order',
        ]);
    }
}
