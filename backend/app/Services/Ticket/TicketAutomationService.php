<?php

namespace App\Services\Ticket;

use App\Models\Ticket;
use App\Services\System\SettingService;
use Illuminate\Support\Facades\Log;

class TicketAutomationService
{
    public function __construct(
        private SettingService $settingService,
        private TicketService $ticketService,
    ) {}

    public function handle(): array
    {
        $config = $this->settingService->getAutomationConfig();

        if (! $config['ticket_auto_close_enabled']) {
            return ['closed' => 0];
        }

        $hours = max(1, $config['ticket_auto_close_after_hours']);

        // 只关闭"员工已回复"且超时无客户跟进的工单
        // STATUS_STAFF_REPLY = 2，等待客户确认
        $tickets = Ticket::query()
            ->where('status', TicketService::STATUS_STAFF_REPLY)
            ->where('updated_at', '<=', now()->subHours($hours))
            ->get();

        $count = 0;

        foreach ($tickets as $ticket) {
            $this->ticketService->autoClose($ticket);

            Log::info('[定时任务] 工单自动关闭', [
                'ticket_id' => $ticket->id,
                'subject' => $ticket->subject,
                'idle_hours' => $hours,
                'last_update' => $ticket->updated_at?->toDateTimeString(),
            ]);

            $count++;
        }

        return ['closed' => $count];
    }
}
