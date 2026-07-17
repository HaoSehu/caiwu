<?php

namespace App\Http\Requests\Admin\Product;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use Illuminate\Validation\Rule;

class ReorderRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1', 'exists:products,id'],
            'target_first_product_group_id' => ['nullable', 'integer', 'min:1'],
            'target_second_product_group_id' => ['nullable', 'integer', 'min:1', Rule::exists((new SecondProductGroup)->getTable(), 'id')],
            'target_third_product_group_id' => ['required', 'integer', 'min:1', Rule::exists((new ThirdProductGroup)->getTable(), 'id')],
            'reference_product_id' => ['nullable', 'integer', 'min:1', 'exists:products,id'],
            'position' => ['required', 'string', 'in:before,after,append'],
        ];
    }
}
