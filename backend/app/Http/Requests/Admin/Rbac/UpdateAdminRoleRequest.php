<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Rbac;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRoleRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $roleId = (int) ($this->route('role')?->id ?? 0);

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_.-]+$/',
                Rule::unique('roles', 'name')->ignore($roleId),
            ],
            'label' => ['required', 'string', 'max:50'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only(['name', 'label', 'permissions']);
    }
}
