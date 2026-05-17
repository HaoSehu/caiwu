<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UserTicketsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'status' => ['nullable', 'in:0,1,2,3'],
            'priority' => ['nullable', 'in:1,2,3,4'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'status',
            'priority',
        ]);
    }
}
