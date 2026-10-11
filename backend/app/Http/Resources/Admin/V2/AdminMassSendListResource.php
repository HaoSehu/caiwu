<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\MassSend;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMassSendListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MassSend $massSend */
        $massSend = $this->resource;

        return [
            'id' => (int) $massSend->id,
            'name' => (string) $massSend->name,
            'channel' => (int) $massSend->channel,
            'channel_label' => $this->channelLabel((int) $massSend->channel),
            'is_marketing' => (int) $massSend->is_marketing,
            'template_id' => $massSend->template_id !== null ? (int) $massSend->template_id : null,
            'template_name' => (string) ($massSend->template->name ?? ''),
            'total_count' => (int) $massSend->total_count,
            'sent_count' => (int) $massSend->sent_count,
            'failed_count' => (int) $massSend->failed_count,
            'status' => (int) $massSend->status,
            'status_label' => $massSend->statusLabel(),
            'scheduled_at' => $massSend->scheduled_at?->toDateTimeString(),
            'started_at' => $massSend->started_at?->toDateTimeString(),
            'finished_at' => $massSend->finished_at?->toDateTimeString(),
            'created_by' => (int) $massSend->created_by,
            'created_at' => $massSend->created_at?->toDateTimeString(),
            'can_edit' => $massSend->isEditable(),
            'can_send' => $massSend->isEditable(),
            'can_cancel' => in_array((int) $massSend->status, [
                MassSend::STATUS_DRAFT,
                MassSend::STATUS_PENDING,
                MassSend::STATUS_SENDING,
            ], true),
        ];
    }

    private function channelLabel(int $channel): string
    {
        return [1 => '邮件', 2 => '站内信', 3 => '两者'][$channel] ?? (string) $channel;
    }
}
