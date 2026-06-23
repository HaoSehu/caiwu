<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ServicePowerRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:on,off,reboot,hard_off,hard_reboot'],
        ];
    }
}
