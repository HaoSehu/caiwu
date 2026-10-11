<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\TicketPresetReply;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class SavePresetReplyRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'min:1'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:100'],
            // content 在创建时必填、更新时可缺省（缺省保留原值，由服务层守卫空内容）
            'content' => ['sometimes', 'string', 'max:10000'],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * 部分更新语义：缺省键不进 payload（服务层保留原值），不得强转默认值覆盖既有数据。
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        $payload = [
            'title' => (string) $validated['title'],
        ];
        if (array_key_exists('category_id', $validated)) {
            $payload['category_id'] = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        }
        if (array_key_exists('department_id', $validated)) {
            $payload['department_id'] = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        }
        if (array_key_exists('content', $validated)) {
            $payload['content'] = (string) $validated['content'];
        }
        if (array_key_exists('is_enabled', $validated)) {
            $payload['is_enabled'] = isset($validated['is_enabled']) ? (int) (bool) $validated['is_enabled'] : null;
        }
        if (array_key_exists('sort_order', $validated)) {
            $payload['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        }

        return $payload;
    }
}
