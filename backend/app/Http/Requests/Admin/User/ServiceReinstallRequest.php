<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ServiceReinstallRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'os_id' => ['required', 'string', 'max:50'],
        ];
    }
}
