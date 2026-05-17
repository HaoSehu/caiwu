<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ListUsersRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'keyword' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:0,1'],
            'is_verified' => ['nullable', 'in:0,1'],
            'verification_status' => ['nullable', 'in:0,1,2,3'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'keyword',
            'user_id',
            'status',
            'is_verified',
            'verification_status',
        ]);
    }
}
