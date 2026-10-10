<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Http\Resources\Concerns\StripsSensitiveResourceData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSupplierBalanceResource extends JsonResource
{
    use StripsSensitiveResourceData;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = is_array($this->resource) ? $this->resource : [];

        return [
            'supplier_id' => (int) ($item['supplier_id'] ?? 0),
            'supplier_name' => (string) ($item['supplier_name'] ?? ''),
            'balance' => (string) ($item['balance'] ?? '0.00'),
            'client' => $this->sanitizeArray($item['client'] ?? []),
            'connection_status' => $item['connection_status'] ?? null,
            'connection_message' => $item['connection_message'] ?? null,
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    private function sanitizeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $sanitized = [];
        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey($key)) {
                continue;
            }

            $sanitized[$key] = is_array($item) ? $this->sanitizeArray($item) : $item;
        }

        return $sanitized;
    }
}
