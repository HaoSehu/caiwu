<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Database;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class ShowDatabaseBackupTaskRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'taskId' => ['required', 'uuid'],
            'page' => ['prohibited'],
            'page_size' => ['prohibited'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    /**
     * 任务 ID 是路由参数，不是查询串参数。
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'taskId' => $this->route('taskId'),
        ]);
    }

    public function taskId(): string
    {
        return (string) $this->validated('taskId');
    }
}
