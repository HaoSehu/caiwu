<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Staff;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ResetAdminStaffPasswordRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only(['password']);
    }
}
