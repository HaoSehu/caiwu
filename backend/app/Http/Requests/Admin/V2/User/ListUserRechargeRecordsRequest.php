<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class ListUserRechargeRecordsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'direction' => ['nullable', 'in:in,out'],
        ], $this->legacyPaginationRules());
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only([
            'direction',
        ]);
    }
}
