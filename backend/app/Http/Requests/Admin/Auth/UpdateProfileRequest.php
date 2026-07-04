<?php

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UpdateProfileRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'nickname' => ['nullable', 'string', 'max:50'],
            'email' => ['missing'],
        ];
    }
}
