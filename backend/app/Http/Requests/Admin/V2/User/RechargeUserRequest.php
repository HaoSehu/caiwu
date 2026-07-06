<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\User\RechargeUserRequest as BaseRechargeUserRequest;

class RechargeUserRequest extends BaseRechargeUserRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'page' => ['prohibited'],
            'page_size' => ['prohibited'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ]);
    }
}
