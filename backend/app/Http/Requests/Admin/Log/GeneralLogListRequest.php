<?php

namespace App\Http\Requests\Admin\Log;

use Illuminate\Validation\Validator;

class GeneralLogListRequest extends LogListRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(100), [
            'keyword' => ['nullable', 'string', 'max:120'],
            'actor_keyword' => ['nullable', 'string', 'max:120'],
            'description_keyword' => ['nullable', 'string', 'max:120'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'level' => ['nullable', 'in:DEBUG,INFO,NOTICE,WARNING,ERROR,CRITICAL,ALERT,EMERGENCY'],
            'module' => ['nullable', 'string', 'max:60'],
            'method' => ['nullable', 'in:GET,POST,PUT,PATCH,DELETE,OPTIONS,HEAD'],
            'status' => ['nullable', 'string', 'max:20'],
            'task_key' => ['nullable', 'string', 'max:60'],
            'user_type' => ['nullable', 'in:admin,client,guest'],
            'gateway' => ['nullable', 'string', 'max:50'],
            'gateway_key' => ['nullable', 'string', 'max:120'],
            'driver_key' => ['nullable', 'string', 'max:120'],
            'plugin_id' => ['nullable', 'integer', 'min:1'],
            'trace_id' => ['nullable', 'string', 'max:64'],
            'action' => ['nullable', 'string', 'max:100'],
            'result_status' => ['nullable', 'in:success,failed,pending,unknown'],
            'actor_type' => ['nullable', 'in:admin,client,system,sub_account'],
            'subject_type' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'keyword',
            'actor_keyword',
            'description_keyword',
            'ip_address',
            'level',
            'module',
            'method',
            'status',
            'task_key',
            'user_type',
            'gateway',
            'gateway_key',
            'driver_key',
            'plugin_id',
            'trace_id',
            'action',
            'result_status',
            'actor_type',
            'subject_type',
            'start_date',
            'end_date',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->rejectPrivacyFilters($validator, [
            'ip_address' => 'IP 地址',
        ], [
            'keyword' => 'IP、邮箱、手机号等隐私关键词',
            'actor_keyword' => '账号隐私关键词',
        ]));
    }
}
