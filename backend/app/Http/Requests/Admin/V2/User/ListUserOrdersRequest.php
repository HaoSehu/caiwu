<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class ListUserOrdersRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'status' => ['nullable', 'in:0,1,4,5'],
            'type' => ['nullable', 'in:new,renew,upgrade'],
            'upgrade_kind' => ['nullable', 'string', 'max:80'],
        ], $this->legacyPaginationRules());
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only([
            'status',
            'type',
            'upgrade_kind',
        ]);
    }
}
