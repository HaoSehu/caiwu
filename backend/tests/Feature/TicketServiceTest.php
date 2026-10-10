<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\Ticket\TicketService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 工单域回归（B5：V7-01 零覆盖缺口，测试指南「工单创建、回复、指派、关闭」必测域）：
 * 1. 创建 → 客户回复 → 员工回复 → 客户关闭 全生命周期状态流转（0→1→2→3）；
 * 2. 关闭后拒绝再回复（行锁内重读防线）；
 * 3. 归属隔离：非本人工单读取 404、回复/关闭「无权操作」；
 * 4. 自动关闭幂等（已关闭不重复处理）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class TicketServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_full_lifecycle_status_transitions(): void
    {
        $user = $this->makeUser();
        $service = app(TicketService::class);

        $ticket = $service->create($user->id, [
            'department' => 'support',
            'subject' => '无法连接服务器',
            'content' => '今天无法连接到服务器面板。',
        ]);

        $this->assertSame(TicketService::STATUS_OPEN, (int) $ticket->status);
        $this->assertSame((int) $user->id, (int) $ticket->user_id);
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'is_staff' => 0,
        ]);

        $service->clientReply($ticket, $user->id, '补充：错误码 500');
        $this->assertSame(TicketService::STATUS_CLIENT_REPLY, (int) $ticket->refresh()->status);

        $service->staffReply($ticket->refresh(), 999999, '已处理，请重试');
        $this->assertSame(TicketService::STATUS_STAFF_REPLY, (int) $ticket->refresh()->status);
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'is_staff' => 1,
        ]);

        $service->clientClose($ticket->refresh(), $user->id);
        $this->assertSame(TicketService::STATUS_CLOSED, (int) $ticket->refresh()->status);

        // 关闭后拒绝再回复
        try {
            $service->clientReply($ticket->refresh(), $user->id, '再补一句');
            $this->fail('已关闭工单不得再回复');
        } catch (BusinessException $exception) {
            $this->assertSame('工单已关闭', $exception->getMessage());
        }

        // 自动关闭对已关闭工单幂等（不重复处理、不抛错）
        $service->autoClose($ticket->refresh());
        $this->assertSame(TicketService::STATUS_CLOSED, (int) $ticket->refresh()->status);
    }

    public function test_ownership_isolation_is_enforced(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $service = app(TicketService::class);

        $ticket = $service->create($owner->id, [
            'department' => 'billing',
            'subject' => '归属隔离测试',
            'content' => '账单问题咨询。',
        ]);

        // 非本人读取：404（findForUser）
        $notFound = false;
        try {
            $service->findForUser($stranger->id, (int) $ticket->id);
        } catch (ModelNotFoundException) {
            $notFound = true;
        }
        $this->assertTrue($notFound, '非本人工单必须 404');

        // 非本人回复：无权操作
        try {
            $service->clientReply($ticket, $stranger->id, '越权回复');
            $this->fail('非本人工单不得回复');
        } catch (BusinessException $exception) {
            $this->assertSame('无权操作', $exception->getMessage());
        }

        // 非本人关闭：无权操作
        try {
            $service->clientClose($ticket, $stranger->id);
            $this->fail('非本人工单不得关闭');
        } catch (BusinessException $exception) {
            $this->assertSame('无权操作', $exception->getMessage());
        }
    }

    public function test_create_requires_subject_and_valid_payload(): void
    {
        $user = $this->makeUser();
        $service = app(TicketService::class);

        try {
            $service->create($user->id, [
                'department' => 'support',
                'subject' => '   ',
                'content' => '内容',
            ]);
            $this->fail('空标题必须被拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame('工单标题不能为空', $exception->getMessage());
        }
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'ticket-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'ticket-tester',
            'total_sales_amount' => 0,
            'status' => 1,
        ]);
    }
}
