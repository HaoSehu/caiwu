<?php

declare(strict_types=1);

namespace App\Http\Resources\Client\V2;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 客户端工单列表项。
 *
 * 此前 `/client/tickets` 直接输出 Eloquent `Ticket` 模型（11 个表列 + `replies` 关联），
 * 没有 Resource 层，导致契约无法被 Apifox 数据模型绑定。
 *
 * 本 Resource 显式列出这 11 个字段（与 `tickets` 表列一一对应），并保留 `replies`
 * 关联——`TicketService::clientList()` 会预载最新一条回复，页面用于展示最后回复摘要。
 *
 * @mixin Ticket
 */
class ClientTicketListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->resource;

        return [
            'id' => (int) $ticket->id,
            'user_id' => (int) $ticket->user_id,
            'department' => (string) $ticket->department,
            'subject' => (string) ($ticket->subject ?? ''),
            'priority' => (int) $ticket->priority,
            'status' => (int) $ticket->status,
            'service_id' => $ticket->service_id,
            'assignee_id' => $ticket->assignee_id,
            'close_reason' => $ticket->close_reason,
            'created_at' => $ticket->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $ticket->updated_at?->format('Y-m-d H:i:s'),
            // clientList 预载的最新一条回复（limit 1），供列表展示最后回复摘要。
            'replies' => $this->replies($ticket),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function replies(Ticket $ticket): array
    {
        if (! $ticket->relationLoaded('replies')) {
            return [];
        }

        $rows = [];

        foreach ($ticket->replies as $reply) {
            // created_at 在模型里是 datetime cast（Carbon），但 $fillable 声明让静态分析
            // 推断为 string；用 formatDateTime 统一处理两种形态，保证输出与迁移前一致。
            $rows[] = [
                'id' => (int) ($reply->id ?? 0),
                'ticket_id' => (int) ($reply->ticket_id ?? 0),
                'user_id' => (int) ($reply->user_id ?? 0),
                'content' => (string) ($reply->content ?? ''),
                'is_staff' => (int) ($reply->is_staff ?? 0),
                'created_at' => $this->formatDateTime(
                    $reply->getAttribute('created_at'),
                ),
            ];
        }

        return $rows;
    }

    /**
     * 时间统一输出：datetime cast 为 Carbon，字符串原样，空值 null。
     */
    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
