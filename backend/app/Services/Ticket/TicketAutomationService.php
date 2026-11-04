<?php

namespace App\Services\Ticket;

use App\Jobs\SendTicketNotificationEmailJob;
use App\Models\AutomationLog;
use App\Models\Ticket;
use App\Services\Notification\UserNotificationService;
use App\Services\System\NotificationService;
use App\Services\System\SettingService;
use Illuminate\Support\Facades\Log;

class TicketAutomationService
{
    public function __construct(
        private SettingService $settingService,
        private TicketService $ticketService,
        private UserNotificationService $userNotificationService,
    ) {}

    public function handle(): array
    {
        $config = $this->settingService->getAutomationConfig();

        if (! $config['ticket_auto_close_enabled']) {
            return ['closed' => 0, 'pending_reminded' => 0];
        }

        $hours = max(1, $config['ticket_auto_close_after_hours']);

        return [
            'pending_reminded' => $this->remindPendingTickets($hours),
            'closed' => $this->closeIdleTickets($hours),
        ];
    }

    /**
     * 关闭"员工已回复"且超时无客户跟进的工单。
     */
    private function closeIdleTickets(int $hours): int
    {
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

        return $count;
    }

    /**
     * 给"员工已回复"但客户超过半个自动关闭窗口仍未跟进的工单，发一条催办站内信。
     * 临近自动关闭前提醒客户处理；同一工单的同一轮等待只提醒一次。
     */
    private function remindPendingTickets(int $hours): int
    {
        // 提醒阈值取自动关闭窗口的一半（至少 1 小时），且尚未到关闭阈值
        $remindAfterHours = max(1, (int) floor($hours / 2));
        if ($remindAfterHours >= $hours) {
            return 0;
        }

        $now = now();

        $tickets = Ticket::query()
            ->with('user:id')
            ->where('status', TicketService::STATUS_STAFF_REPLY)
            ->where('updated_at', '<=', $now->copy()->subHours($remindAfterHours))
            ->where('updated_at', '>', $now->copy()->subHours($hours))
            ->get();

        $count = 0;

        foreach ($tickets as $ticket) {
            $userId = (int) ($ticket->user_id ?? 0);
            if ($userId <= 0) {
                continue;
            }

            // 以本轮等待的起点（updated_at）作为幂等键，客户一旦回复 updated_at 变化即重新计算
            $ruleKey = 'idle_since:'.($ticket->updated_at?->format('YmdHis') ?? '0');
            if (! AutomationLog::recordOnce('ticket-maintenance', 'ticket_pending_remind', 'ticket', (int) $ticket->id, $ruleKey)) {
                continue;
            }

            $hoursLeft = max(1, $hours - $remindAfterHours);
            $ticketUser = $ticket->user;
            $email = trim((string) ($ticketUser->email ?? ''));
            $remindBody = "工单「{$ticket->subject}」客服已回复，若 {$hoursLeft} 小时内无后续回复将自动关闭，请及时查看。";

            // 邮件：受管理端「工单待处理提醒」场景的邮件开关控制
            if ($email !== '') {
                SendTicketNotificationEmailJob::dispatch(
                    $email,
                    NotificationService::TEMPLATE_TICKET_PENDING_REMINDER,
                    [
                        'display_name' => (string) ($ticketUser->display_name ?? '客户'),
                        'ticket_id' => (int) $ticket->id,
                        'ticket_subject' => (string) $ticket->subject,
                        'hours_left' => $hoursLeft,
                    ],
                    ['scene' => 'ticket_pending_reminder', 'ticket_id' => (int) $ticket->id, 'recipient_user_id' => $userId]
                );
            }

            // 站内消息：受管理端「工单待处理提醒」场景的站内消息开关控制
            $this->userNotificationService->createForScene(
                $userId,
                'ticket_pending_reminder',
                '工单待您处理',
                $remindBody,
                '/client/tickets/'.$ticket->id,
                ['ticket_subject' => (string) $ticket->subject],
                ['ticket_id' => (int) $ticket->id]
            );

            AutomationLog::markExecuted('ticket-maintenance', 'ticket_pending_remind', 'ticket', (int) $ticket->id, $ruleKey, [
                'idle_hours' => $remindAfterHours,
                'auto_close_hours' => $hours,
            ]);

            $count++;
        }

        return $count;
    }
}
