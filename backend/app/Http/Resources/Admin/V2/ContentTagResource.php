<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\ContentTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentTag */
class ContentTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ContentTag $tag */
        $tag = $this->resource;

        return [
            'id' => (int) $tag->id,
            'name' => (string) $tag->name,
            'usage_count' => (int) $tag->usage_count,
            'sort_order' => (int) $tag->sort_order,
            'created_at' => $tag->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $tag->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
