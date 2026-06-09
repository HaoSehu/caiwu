<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Rbac;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ListAdminRolesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function filters(): array
    {
        return $this->safe()->only(['keyword']);
    }
}
