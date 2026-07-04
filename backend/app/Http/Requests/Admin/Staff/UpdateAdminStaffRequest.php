<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Staff;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Support\AdminPermissions;
use Illuminate\Validation\Rule;

class UpdateAdminStaffRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $staffId = (int) ($this->route('staff')?->id ?? 0);
        $canEditAccount = $this->user()?->hasPermission(AdminPermissions::ALL) === true;

        return array_filter([
            'username' => $canEditAccount ? [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_.@-]+$/',
                Rule::unique('admin_users', 'username')->ignore($staffId),
            ] : ['missing'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'email' => $canEditAccount ? ['nullable', 'email', 'max:100', Rule::unique('admin_users', 'email')->ignore($staffId)] : ['missing'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);
    }

    public function payload(): array
    {
        $fields = ['nickname', 'role_id', 'status'];

        if ($this->user()?->hasPermission(AdminPermissions::ALL) === true) {
            array_unshift($fields, 'username');
            $fields[] = 'email';
        }

        return $this->safe()->only($fields);
    }

    public function messages(): array
    {
        return [
            'username.regex' => '登录账号仅支持字母、数字、下划线、点、横线和 @',
        ];
    }
}
