<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Auth;

use App\Http\Requests\Admin\Auth\LoginRequest as LegacyLoginRequest;

class LoginRequest extends LegacyLoginRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'per_page' => ['prohibited'],
        ]);
    }
}
