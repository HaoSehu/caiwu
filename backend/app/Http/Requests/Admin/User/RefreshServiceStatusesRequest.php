<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class RefreshServiceStatusesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'service_ids' => ['required', 'array', 'min:1', 'max:50'],
            'service_ids.*' => ['integer', 'min:1'],
        ];
    }
}
