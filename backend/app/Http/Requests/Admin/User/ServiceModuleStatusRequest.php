<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ServiceModuleStatusRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:host,reinstall,repassword'],
        ];
    }
}
