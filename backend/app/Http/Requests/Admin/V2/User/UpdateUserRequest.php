<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\User\UpdateUserRequest as BaseUpdateUserRequest;
use App\Support\AccountIdentifier;

class UpdateUserRequest extends BaseUpdateUserRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => AccountIdentifier::normalizeOptionalPhone((string) $this->input('phone')),
            ]);
        }
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['phone'][0] = 'sometimes';
        array_splice($rules['phone'], 1, 0, 'required');

        return array_merge($rules, [
            'page' => ['prohibited'],
            'page_size' => ['prohibited'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ]);
    }
}
