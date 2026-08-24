<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * V2 归档数据资源：
 * - 批次形状（list）：批次物摘要；
 * - 命中形状（search）：归档 CSV 中命中的记录行。
 * 两种形状都不返回物理路径。
 */
class AdminLogArchiveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = is_array($this->resource) ? $this->resource : [];

        // search 命中形状：单条归档记录
        if (array_key_exists('id', $row) && array_key_exists('table', $row)) {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'table' => (string) ($row['table'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'batch_id' => (string) ($row['batch_id'] ?? ''),
                'file' => (string) ($row['file'] ?? ''),
                'restorable' => (bool) ($row['restorable'] ?? false),
                'restorable_check' => isset($row['restorable_check']) ? (string) $row['restorable_check'] : null,
                'restorable_reason' => isset($row['restorable_reason']) ? (string) $row['restorable_reason'] : null,
            ];
        }

        // list 形状：批次物摘要
        return [
            'batch_id' => (string) ($row['batch_id'] ?? ''),
            'table' => (string) ($row['table'] ?? $row['table_name'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'id_min' => $row['id_min'] ?? null,
            'id_max' => $row['id_max'] ?? null,
            'expected_rows' => (int) ($row['expected_rows'] ?? 0),
            'exported_rows' => (int) ($row['exported_rows'] ?? 0),
            'deleted_rows' => (int) ($row['deleted_rows'] ?? 0),
            'restorable' => (bool) ($row['restorable'] ?? false),
            'restorable_check' => isset($row['restorable_check']) ? (string) $row['restorable_check'] : null,
            'restorable_reason' => isset($row['restorable_reason']) ? (string) $row['restorable_reason'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'purged_at' => (string) ($row['purged_at'] ?? ''),
        ];
    }
}
