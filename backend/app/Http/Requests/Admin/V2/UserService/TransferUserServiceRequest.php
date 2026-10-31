<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\UserService;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

/**
 * 管理员转移服务实例归属
 */
class TransferUserServiceRequest extends AdminFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'target_user_id' => ['required', 'integer', 'min:1'],
            'remark' => ['nullable', 'string', 'max:200'],
            ...$this->allPaginationRules(),
        ];
    }

    /**
     * @return array{target_user_id: int, remark: string}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'target_user_id' => (int) ($validated['target_user_id'] ?? 0),
            'remark' => trim((string) ($validated['remark'] ?? '')),
        ];
    }
}
