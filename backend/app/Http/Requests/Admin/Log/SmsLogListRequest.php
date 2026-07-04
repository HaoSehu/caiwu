<?php

namespace App\Http\Requests\Admin\Log;

use Illuminate\Validation\Validator;

class SmsLogListRequest extends LogListRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'phone' => ['nullable', 'string', 'max:20'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,success,failed'],
            'plugin_id' => ['nullable', 'integer', 'min:1'],
            'driver_key' => ['nullable', 'string', 'max:120'],
            'trace_id' => ['nullable', 'string', 'max:64'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'phone',
            'keyword',
            'status',
            'plugin_id',
            'driver_key',
            'trace_id',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->rejectPrivacyFilters($validator, [
            'phone' => '手机号',
        ], [
            'keyword' => '手机号等隐私关键词',
        ]));
    }
}
