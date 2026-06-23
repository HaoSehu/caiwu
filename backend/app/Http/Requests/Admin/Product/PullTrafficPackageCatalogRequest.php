<?php

namespace App\Http\Requests\Admin\Product;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class PullTrafficPackageCatalogRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'second_product_group_id' => ['required_without:third_product_group_id', 'integer', 'min:1', Rule::exists((new SecondProductGroup)->getTable(), 'id')],
            'third_product_group_id' => ['nullable', 'integer', 'min:1', Rule::exists((new ThirdProductGroup)->getTable(), 'id')],
            'product_type' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['nullable', 'integer', 'min:1'],
            'supplier_product_id' => ['nullable', 'integer', 'min:1'],
            'source_product_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
