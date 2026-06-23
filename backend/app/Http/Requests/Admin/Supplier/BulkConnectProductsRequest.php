<?php

namespace App\Http\Requests\Admin\Supplier;

use App\Constants\ProductType;
use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Models\FirstProductGroup;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use Illuminate\Validation\Rule;

class BulkConnectProductsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'product_type' => ['required', 'string', Rule::in(ProductType::allowedValues())],
            'first_product_group_id' => ['nullable', 'integer', 'min:1', Rule::exists((new FirstProductGroup)->getTable(), 'id')],
            'second_product_group_id' => ['nullable', 'integer', 'min:1', Rule::exists((new SecondProductGroup)->getTable(), 'id')],
            'third_product_group_id' => ['nullable', 'integer', 'min:1', Rule::exists((new ThirdProductGroup)->getTable(), 'id')],
            'second_product_group_name' => ['nullable', 'string', 'max:100', 'required_without_all:second_product_group_id,third_product_group_id'],
            'third_product_group_name' => ['nullable', 'string', 'max:100'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'min:1'],
            'default_status' => ['nullable', 'in:0,1'],
            'default_auto_setup' => ['nullable', 'in:0,1'],
            'sync_config_options' => ['nullable', 'in:0,1'],
        ];
    }
}
