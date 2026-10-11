<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Schedule;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Validator;

class ClearQueueJobsRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_text' => ['required', 'string'],
        ];
    }

    /**
     * 清空队列为不可逆破坏性操作，与日志清理一致要求显式确认文本。
     */
    public function confirmText(): string
    {
        return trim((string) $this->validated()['confirm_text']);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->confirmText() !== '立即清空') {
                $validator->errors()->add('confirm_text', '确认文本必须为 立即清空');
            }
        });
    }
}
