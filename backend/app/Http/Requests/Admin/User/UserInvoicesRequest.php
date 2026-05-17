<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UserInvoicesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'status' => ['nullable', 'in:0,1,2,3,5'],
            'type' => ['nullable', 'in:normal,renew,manual'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'status',
            'type',
        ]);
    }
}
