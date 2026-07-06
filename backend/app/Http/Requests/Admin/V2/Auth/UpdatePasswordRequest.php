<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Auth;

use App\Http\Requests\Admin\Auth\UpdatePasswordRequest as LegacyUpdatePasswordRequest;

class UpdatePasswordRequest extends LegacyUpdatePasswordRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'per_page' => ['prohibited'],
        ]);
    }
}
