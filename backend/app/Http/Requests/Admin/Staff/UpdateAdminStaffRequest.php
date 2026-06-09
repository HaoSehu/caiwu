<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Staff;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminStaffRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $staffId = (int) ($this->route('staff')?->id ?? 0);

        return [
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_.-]+$/',
                Rule::unique('admin_users', 'username')->ignore($staffId),
            ],
            'nickname' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('admin_users', 'email')->ignore($staffId)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only(['username', 'nickname', 'email', 'role_id', 'status']);
    }
}
