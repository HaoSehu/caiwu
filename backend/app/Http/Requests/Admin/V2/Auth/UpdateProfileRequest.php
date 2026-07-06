<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Auth;

use App\Http\Requests\Admin\Auth\UpdateProfileRequest as LegacyUpdateProfileRequest;

class UpdateProfileRequest extends LegacyUpdateProfileRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'per_page' => ['prohibited'],
        ]);
    }
}
