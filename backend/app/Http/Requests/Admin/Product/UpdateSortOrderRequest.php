<?php

namespace App\Http\Requests\Admin\Product;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UpdateSortOrderRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ];
    }
}
