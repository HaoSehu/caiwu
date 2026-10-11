<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\Ticket;
use App\Services\Ticket\TicketService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class AdminTicketTransferResource extends JsonResource
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
            'department' => (string) $ticket->department,
            'department_label' => TicketService::DEPT_LABELS[$ticket->department] ?? (string) $ticket->department,
            'department_id' => $ticket->department_id !== null ? (int) $ticket->department_id : null,
            'assignee_id' => $ticket->assignee_id !== null ? (int) $ticket->assignee_id : null,
            'status' => (int) $ticket->status,
            'status_label' => TicketService::STATUS_LABELS[(int) $ticket->status] ?? (string) $ticket->status,
        ];
    }
}
