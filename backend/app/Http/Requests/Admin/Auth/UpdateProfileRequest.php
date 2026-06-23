<?php

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Support\AccountIdentifier;

class UpdateProfileRequest extends AdminFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => AccountIdentifier::normalizeOptionalEmail((string) $this->input('email')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nickname' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
        ];
    }
}
