<?php

namespace App\Http\Requests\Admin\Log;

use Illuminate\Foundation\Http\FormRequest;

class CleanupLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:sms,email,api,admin_login,schedule_run,task,system,all_db,all_file,all'],
            'keep_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'confirm_text' => ['required', 'string', 'in:立即清理'],
        ];
    }

    public function payload(): array
    {
        return $this->validated();
    }
}
