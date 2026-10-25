<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\UserService;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class ListUserServiceOperationLogsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'keyword' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'in:power,password,reinstall,renew,upgrade,nat_forwarding,security_group,security_rule,vnc,service'],
        ], $this->legacyPaginationRules());
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only([
            'keyword',
            'category',
        ]);
    }
}
