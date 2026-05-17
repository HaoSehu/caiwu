<?php

namespace App\Http\Requests\Admin\Log;

class EmailLogListRequest extends LogListRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'email' => ['nullable', 'string', 'max:100'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,success,failed'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'email',
            'keyword',
            'status',
        ]);
    }
}
