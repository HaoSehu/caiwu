<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\User\StoreUserRequest as BaseStoreUserRequest;

class StoreUserRequest extends BaseStoreUserRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['phone'][0] = 'required';

        return array_merge($rules, [
            'page' => ['prohibited'],
            'page_size' => ['prohibited'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ]);
    }
}
