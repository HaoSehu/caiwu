<?php

namespace App\Http\Requests\Admin\InstanceSpecCatalog;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class IndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:120'],
            'binding_status' => ['nullable', 'string', 'in:bound,unbound'],
        ];
    }
}
