<?php

namespace App\Http\Requests\Admin\Product;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ListProductOwnersRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'keyword' => ['nullable', 'string', 'max:100'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only(['keyword']);
    }
}
