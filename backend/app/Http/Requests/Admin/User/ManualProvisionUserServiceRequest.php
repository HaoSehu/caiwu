<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ManualProvisionUserServiceRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'remark' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only(['remark']);
    }
}
