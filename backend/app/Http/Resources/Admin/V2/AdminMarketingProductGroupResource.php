<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\MarketingProductGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MarketingProductGroup */
class AdminMarketingProductGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'sort_order' => (int) $this->sort_order,
            'product_count' => $this->whenCounted('items', fn () => (int) $this->items_count),
            'product_ids' => $this->when(
                isset($this->additional_product_ids),
                fn () => array_map('intval', (array) ($this->additional_product_ids ?? [])),
            ),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
