<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\User\ListUsersRequest as BaseListUsersRequest;

class ListUsersRequest extends BaseListUsersRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'per_page' => ['prohibited'],
            'pageSize' => ['prohibited'],
        ]);
    }
}
