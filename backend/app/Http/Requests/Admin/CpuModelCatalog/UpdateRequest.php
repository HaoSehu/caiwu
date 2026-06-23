<?php

namespace App\Http\Requests\Admin\CpuModelCatalog;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UpdateRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'list' => ['required', 'array'],
            'list.*.id' => ['nullable', 'string', 'max:80'],
            'list.*.value' => ['nullable', 'string', 'max:60'],
            'list.*.name' => ['required', 'string', 'max:80'],
            'list.*.models' => ['nullable', 'array'],
            'list.*.models.*.id' => ['nullable', 'string', 'max:80'],
            'list.*.models.*.value' => ['nullable', 'string', 'max:60'],
            'list.*.models.*.name' => ['required', 'string', 'max:80'],
            'list.*.models.*.base_frequency' => ['nullable', 'string', 'max:40'],
            'list.*.models.*.turbo_frequency' => ['nullable', 'string', 'max:40'],
            'list.*.models.*.bindings' => ['nullable', 'array'],
            'list.*.models.*.bindings.*.product_id' => ['required', 'integer', 'min:1'],
            'list.*.models.*.bindings.*.category_full_name' => ['nullable', 'string', 'max:160'],
            'list.*.models.*.bindings.*.primary_price' => ['nullable', 'array'],
            'list.*.models.*.bindings.*.primary_price.cycle' => ['nullable', 'string', 'max:40'],
            'list.*.models.*.bindings.*.primary_price.amount' => ['nullable', 'string', 'max:40'],
            'list.*.models.*.bindings.*.status' => ['nullable', 'integer', 'in:0,1'],
        ];
    }
}
