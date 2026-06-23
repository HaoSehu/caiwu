<?php

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class LoginRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ];
    }
}
