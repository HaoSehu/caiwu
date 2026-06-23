<?php

namespace App\Http\Requests\Admin\ProductType;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UpdateRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:30'],
            'is_hidden' => ['nullable', 'boolean'],
            'icon' => ['nullable', 'string', 'max:50'],
        ];
    }
}
