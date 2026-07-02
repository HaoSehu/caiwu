<?php

namespace App\Http\Requests\Admin\Supplier;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class IndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'keyword' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:0,1'],
        ]);
    }
}
