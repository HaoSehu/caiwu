<?php

namespace App\Http\Requests\Admin\InstanceSpecCatalog;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UpdateRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'list' => ['required', 'array'],
            'list.*.id' => ['nullable', 'string', 'max:80'],
            'list.*.value' => ['nullable', 'string', 'max:60'],
            'list.*.text' => ['required', 'string', 'max:80'],
            'list.*.alias' => ['nullable', 'string', 'max:80'],
            'list.*.note' => ['nullable', 'string', 'max:255'],
            'list.*.status' => ['nullable', 'string', 'max:30'],
            'list.*.bindings' => ['nullable', 'array'],
            'list.*.bindings.*.product_id' => ['required', 'integer', 'min:1'],
            'list.*.bindings.*.category_full_name' => ['nullable', 'string', 'max:160'],
            'list.*.bindings.*.primary_price' => ['nullable', 'array'],
            'list.*.bindings.*.primary_price.cycle' => ['nullable', 'string', 'max:40'],
            'list.*.bindings.*.primary_price.amount' => ['nullable', 'string', 'max:40'],
            'list.*.bindings.*.status' => ['nullable', 'integer', 'in:0,1'],
        ];
    }
}
