<?php

namespace App\Http\Requests\Admin\Log;

class SmsLogListRequest extends LogListRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'phone' => ['nullable', 'string', 'max:20'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,success,failed'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'phone',
            'keyword',
            'status',
        ]);
    }
}
