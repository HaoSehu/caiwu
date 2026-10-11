<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\TicketPresetReplyCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketPresetReplyCategory */
class AdminTicketPresetReplyCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TicketPresetReplyCategory $category */
        $category = $this->resource;

        return [
            'id' => (int) $category->id,
            'name' => (string) $category->name,
            'is_enabled' => (int) $category->is_enabled,
            'sort_order' => (int) $category->sort_order,
            'replies_count' => isset($category->replies_count) ? (int) $category->replies_count : 0,
            'created_at' => $category->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $category->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
