<?php

namespace App\Http\Requests\Admin\ProductCategory;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class ReorderRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'effective_product_group_id' => ['required', 'integer', 'min:1'],
            'effective_product_group_level' => ['required', 'integer', Rule::in([2, 3])],
            'target_first_product_group_id' => ['nullable', 'integer', 'min:1', 'required_if:effective_product_group_level,2'],
            'target_second_product_group_id' => ['nullable', 'integer', 'min:1', 'required_if:effective_product_group_level,3'],
            'reference_second_product_group_id' => ['nullable', 'integer', 'min:1'],
            'reference_third_product_group_id' => ['nullable', 'integer', 'min:1'],
            'position' => ['required', 'string', Rule::in(['before', 'after', 'append'])],
        ];
    }
}
