<?php

declare(strict_types=1);

namespace App\Http\Resources\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 管理端与客户端共用的动作结果资源。
 *
 * 删除、退款、重试、异步任务提交等写操作统一返回该结构；两端语义完全一致，
 * 因此只保留一份实现，避免跨端漂移。字段值为 null 时不输出，避免前端出现
 * 无意义的空键。
 */
class ActionResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = is_array($this->resource) ? $this->resource : [];

        return array_filter([
            'id' => $item['id'] ?? null,
            'status' => (string) ($item['status'] ?? 'completed'),
            'task_id' => $item['task_id'] ?? null,
            'message' => (string) ($item['message'] ?? ''),
            'detail' => $item['detail'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
