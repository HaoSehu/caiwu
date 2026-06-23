<?php

namespace App\Http\Requests\Admin\Product;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class SplitPreviewRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:100'],
            'product_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }
}
