<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLogDetailResource extends JsonResource
{
    // 管理端日志详情返回完整原文，不做脱敏（项目红线：管理员需真实审计信息）
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = is_array($this->resource) ? $this->resource : [];
        $fields = is_array($row['fields'] ?? null) ? $row['fields'] : [];
        $channel = (string) ($row['channel'] ?? '');

        return [
            'id' => (string) ($row['id'] ?? $fields['id'] ?? ''),
            'channel' => $channel,
            'source' => (string) ($row['source'] ?? $fields['source'] ?? ''),
            'fields' => $fields,
            'message' => (string) ($row['message'] ?? ''),
            'context' => $row['context'] ?? [],
            'created_at' => $row['created_at'] ?? $fields['created_at'] ?? null,
        ];
    }
}
