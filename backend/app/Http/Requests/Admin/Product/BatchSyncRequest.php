<?php

namespace App\Http\Requests\Admin\Product;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class BatchSyncRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'min:1'],
            'sync_pricing' => ['nullable', 'in:0,1'],
            'sync_config_options' => ['nullable', 'in:0,1'],
            'sync_config_pricing' => ['nullable', 'in:0,1'],
        ];
    }
}
