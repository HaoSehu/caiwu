<?php

declare(strict_types=1);

namespace App\Services\Ticket;

use App\Constants\UserNotificationType;
use App\Models\AutomationLog;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketDepartmentStaff;
use App\Services\Notification\UserNotificationService;
use App\Services\System\SettingService;
use Illuminate\Support\Facades\Log;
use Throwable;

class TicketAutomationService
{
    private const DISPATCH_CURSOR_GROUP = 'ticket';

    private const DISPATCH_CURSOR_KEY = 'ticket.dispatch.cursor';

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
            $this->userNotificationService->create(
                $userId,
                UserNotificationType::TICKET_PENDING_REMINDER,
                '工单待您处理',
                "工单「{$ticket->subject}」客服已回复，若 {$hoursLeft} 小时内无后续回复将自动关闭，请及时查看。",
                '/client/tickets/'.$ticket->id,
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

    /**
     * M3 工单部门自动分派：按部门 auto_assign_mode 选人。
     * - 0 手动：不动 assignee_id；
     * - 1 轮询：部门启用成员按 admin_user_id 升序循环，取游标的下一个；
     * - 2 最少负载：COUNT 未结工单（status != 已关闭）最少者，并列取 id 小者；
     * 无启用成员时 assignee_id 置 NULL（仅落部门）。
     */
    public function dispatch(Ticket $ticket, TicketDepartment $department): Ticket
    {
        $mode = (int) $department->auto_assign_mode;
        if ($mode === TicketDepartment::AUTO_ASSIGN_MANUAL) {
            return $ticket;
        }

        $candidateIds = TicketDepartmentStaff::query()
            ->enabledStaff((int) $department->id)
            ->pluck('admin_user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($candidateIds === []) {
            $ticket->update(['assignee_id' => null]);

            return $ticket->refresh();
        }

        $assigneeId = $mode === TicketDepartment::AUTO_ASSIGN_ROUND_ROBIN
            ? $this->nextRoundRobin((int) $department->id, $candidateIds)
            : $this->leastLoaded($candidateIds);

        $ticket->update(['assignee_id' => $assigneeId]);

        return $ticket->refresh();
    }

    /**
     * 轮询取人：游标（settings {dept_id: last_admin_id}）之后的第一个成员；游标指向末尾或缺失时回到队首。
     *
     * @param  list<int>  $candidateIds  按 admin_user_id 升序
     */
    private function nextRoundRobin(int $departmentId, array $candidateIds): int
    {
        $lastId = $this->loadDispatchCursor()[$departmentId] ?? null;
        $nextId = null;

        foreach ($candidateIds as $candidateId) {
            if ($lastId === null || $candidateId > (int) $lastId) {
                $nextId = $candidateId;
                break;
            }
        }

        $nextId = $nextId ?? $candidateIds[0];
        $this->saveDispatchCursor($departmentId, $nextId);

        return $nextId;
    }

    /**
     * 最少负载取人。
     *
     * @param  list<int>  $candidateIds  按 admin_user_id 升序，并列时先到者（id 小者）胜出
     */
    private function leastLoaded(array $candidateIds): int
    {
        $counts = Ticket::query()
            ->whereIn('assignee_id', $candidateIds)
            ->where('status', '!=', TicketService::STATUS_CLOSED)
            ->groupBy('assignee_id')
            ->selectRaw('assignee_id, count(*) as aggregate')
            ->pluck('aggregate', 'assignee_id');

        $bestId = (int) $candidateIds[0];
        $bestCount = null;

        foreach ($candidateIds as $candidateId) {
            $count = (int) ($counts[$candidateId] ?? 0);
            if ($bestCount === null || $count < $bestCount) {
                $bestId = $candidateId;
                $bestCount = $count;
            }
        }

        return $bestId;
    }

    /**
     * @return array<int, int>
     */
    private function loadDispatchCursor(): array
    {
        $raw = Setting::getValue(self::DISPATCH_CURSOR_GROUP, self::DISPATCH_CURSOR_KEY, '{}');
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $cursor = [];
        foreach ($decoded as $key => $value) {
            if (is_numeric($key) && is_numeric($value)) {
                $cursor[(int) $key] = (int) $value;
            }
        }

        return $cursor;
    }

    private function saveDispatchCursor(int $departmentId, int $adminId): void
    {
        $cursor = $this->loadDispatchCursor();
        $cursor[$departmentId] = $adminId;

        Setting::setValue(
            self::DISPATCH_CURSOR_GROUP,
            self::DISPATCH_CURSOR_KEY,
            (string) json_encode($cursor, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * 供测试断言轮询游标推进。
     *
     * @return array<int, int>
     */
    public function dispatchCursorSnapshot(): array
    {
        return $this->loadDispatchCursor();
    }
}
