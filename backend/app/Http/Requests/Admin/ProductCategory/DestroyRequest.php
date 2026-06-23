<?php

namespace App\Http\Requests\Admin\ProductCategory;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class DestroyRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'effective_product_group_level' => ['required', 'integer', Rule::in([1, 2, 3])],
        ];
    }
}
