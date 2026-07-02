<?php

namespace App\Http\Requests\Admin\Log;

class GeneralLogListRequest extends LogListRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(100), [
            'keyword' => ['nullable', 'string', 'max:120'],
            'level' => ['nullable', 'in:DEBUG,INFO,NOTICE,WARNING,ERROR,CRITICAL,ALERT,EMERGENCY'],
            'module' => ['nullable', 'string', 'max:60'],
            'method' => ['nullable', 'in:GET,POST,PUT,PATCH,DELETE,OPTIONS,HEAD'],
            'status' => ['nullable', 'integer', 'min:100', 'max:599'],
            'task_key' => ['nullable', 'string', 'max:60'],
            'user_type' => ['nullable', 'in:admin,client,guest'],
            'gateway' => ['nullable', 'string', 'max:50'],
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
            'level',
            'module',
            'method',
            'status',
            'task_key',
            'user_type',
            'gateway',
            'action',
            'result_status',
            'actor_type',
            'subject_type',
            'start_date',
            'end_date',
        ]);
    }
}
