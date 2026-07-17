<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDatabaseStatusResource extends JsonResource
{
    /**
     * @return array{
     *     database: string,
     *     list: list<array{name: string, rows: int, size_mb: float, update_time: ?string}>,
     *     total_count: int,
     *     total_rows: int,
     *     total_size_mb: float
     * }
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : [];
        $list = collect($payload['list'] ?? [])
            ->map(static function (mixed $row): array {
                $item = is_array($row) ? $row : [];

                return [
                    'name' => (string) ($item['name'] ?? ''),
                    'rows' => (int) ($item['rows'] ?? 0),
                    'size_mb' => round((float) ($item['size_mb'] ?? 0), 2),
                    'update_time' => isset($item['update_time']) && $item['update_time'] !== null
                        ? (string) $item['update_time']
                        : null,
                ];
            })
            ->values()
            ->all();

        return [
            'database' => (string) ($payload['database'] ?? ''),
            'list' => $list,
            'total_count' => (int) ($payload['total_count'] ?? count($list)),
            'total_rows' => (int) ($payload['total_rows'] ?? 0),
            'total_size_mb' => round((float) ($payload['total_size_mb'] ?? 0), 2),
        ];
    }
}
