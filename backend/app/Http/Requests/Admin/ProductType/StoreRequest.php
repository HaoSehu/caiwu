<?php

namespace App\Http\Requests\Admin\ProductType;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class StoreRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:30'],
            'icon' => ['nullable', 'string', 'max:50'],
        ];
    }
}
