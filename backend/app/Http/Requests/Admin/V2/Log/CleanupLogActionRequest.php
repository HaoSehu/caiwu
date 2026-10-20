<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Log;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class CleanupLogActionRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            // gateway 为支付证据，管理端只读预览，不得随普通清理物理删除
            'type' => ['required', 'in:sms,email,api,admin_login,activity,schedule_run,all_db'],
            'keep_days' => ['required', 'integer', 'min:1', 'max:3650'],
            // dry_run=true 只预览截止时间与待删行数，不执行删除，因此不需要确认文案。
            'dry_run' => ['sometimes', 'boolean'],
            'confirm_text' => [
                'nullable',
                'string',
                'in:立即清理',
                Rule::requiredIf(fn (): bool => ! $this->boolean('dry_run')),
            ],
            'per_page' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->only([
            'type',
            'keep_days',
            'dry_run',
            'confirm_text',
        ]);
    }
}
