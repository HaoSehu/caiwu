<?php

namespace App\Http\Requests\Admin\Verification;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ListVerificationsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'keyword' => ['nullable', 'string', 'max:100'],
            'is_verified' => ['nullable', 'in:0,1'],
            'verification_status' => ['nullable', 'in:0,1,2,3,5'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'keyword',
            'is_verified',
            'verification_status',
        ]);
    }
}
