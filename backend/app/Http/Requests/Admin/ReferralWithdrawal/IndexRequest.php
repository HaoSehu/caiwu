<?php

namespace App\Http\Requests\Admin\ReferralWithdrawal;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class IndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
