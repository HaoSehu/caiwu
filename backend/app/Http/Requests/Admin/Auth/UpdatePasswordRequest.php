<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UpdatePasswordRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only(['current_password', 'password']);
    }
}
