<?php

namespace App\Http\Requests\Admin\ProductType;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ReorderRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'values' => ['required', 'array', 'min:2'],
            'values.*' => ['required', 'string', 'max:50', 'distinct'],
        ];
    }
}
