<?php

namespace App\Http\Requests\Admin\ScheduleTask;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class TriggerRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'task' => ['required', 'string'],
        ];
    }
}
