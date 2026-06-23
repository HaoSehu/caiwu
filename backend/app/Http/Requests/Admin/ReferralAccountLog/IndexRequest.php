<?php

namespace App\Http\Requests\Admin\ReferralAccountLog;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class IndexRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'event_type' => ['nullable', 'string', 'max:30'],
            'type' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
