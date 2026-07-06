<?php

namespace App\Http\Requests\Admin\ProductCategory;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'effective_product_group_level' => ['required', 'integer', Rule::in([1, 2, 3])],
            'service_type_code' => ['nullable', 'string', 'max:50'],
            'first_product_group_code' => ['nullable', 'string', 'max:50'],
            'first_product_group_id' => ['nullable', 'integer', 'min:1'],
            'second_product_group_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'banner_image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_visible' => ['nullable', Rule::in([0, 1, '0', '1'])],
            'is_system' => ['nullable', Rule::in([0, 1, '0', '1'])],
        ];
    }
}
