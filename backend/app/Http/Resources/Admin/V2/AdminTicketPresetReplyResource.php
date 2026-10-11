<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\TicketPresetReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketPresetReply */
class AdminTicketPresetReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TicketPresetReply $reply */
        $reply = $this->resource;

        return [
            'id' => (int) $reply->id,
            'category_id' => $reply->category_id !== null ? (int) $reply->category_id : null,
            'category_name' => (string) ($reply->category->name ?? ''),
            'department_id' => $reply->department_id !== null ? (int) $reply->department_id : null,
            'department_name' => (string) ($reply->department->name ?? ''),
            'title' => (string) $reply->title,
            'content' => (string) $reply->content,
            'is_enabled' => (int) $reply->is_enabled,
            'sort_order' => (int) $reply->sort_order,
            'usage_count' => (int) $reply->usage_count,
            'created_by' => $reply->created_by !== null ? (int) $reply->created_by : null,
            'creator_name' => (string) ($reply->creator->display_name ?? ''),
            'created_at' => $reply->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $reply->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
