<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\AutomationLog;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\InvoicePaidOrchestrator;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * 无订单账单返佣失败留痕（M-30）：失败不再只写 warning/error 日志，
 * 还必须落 AutomationLog（executed_at 为空）供补偿命令扫描；补偿重试成功后收口。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixReferralRewardFailureLogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invoice_reward_failure_writes_automation_log_for_compensation(): void
    {
        $buyer = $this->makeUser('fail');
        $invoice = $this->makePaidInvoice($buyer);

        $this->mock(ReferralService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('rewardForPaidInvoice')
                ->twice()
                ->andThrow(new BusinessException('模拟返佣写入失败'));
        });

        app(InvoicePaidOrchestrator::class)->processPaidInvoiceReferralRewardById((int) $invoice->id, 'trace-fail-1');

        $record = AutomationLog::query()
            ->where('task_key', 'referral-reward')
            ->where('action', 'failed')
            ->where('object_type', 'invoice')
            ->where('object_id', (int) $invoice->id)
            ->first();

        $this->assertNotNull($record, '返佣失败必须写 AutomationLog 供补偿命令扫描');
        $this->assertNull($record->executed_at, '待补偿记录不得标记为已执行');
        $this->assertStringContainsString('模拟返佣写入失败', (string) data_get($record->meta, 'message', ''));

        // 再次失败：同一失败阶段复用同一条待办，不按次堆积
        app(InvoicePaidOrchestrator::class)->processPaidInvoiceReferralRewardById((int) $invoice->id, 'trace-fail-2');
        $this->assertSame(
            1,
            AutomationLog::query()
                ->where('task_key', 'referral-reward')
                ->where('action', 'failed')
                ->where('object_type', 'invoice')
                ->where('object_id', (int) $invoice->id)
                ->count(),
            '同一账单同一失败阶段的待补偿记录必须收敛为一条'
        );
    }

    public function test_invoice_reward_success_closes_pending_failure_records(): void
    {
        $buyer = $this->makeUser('ok');
        $invoice = $this->makePaidInvoice($buyer);

        AutomationLog::query()->create([
            'task_key' => 'referral-reward',
            'action' => 'failed',
            'object_type' => 'invoice',
            'object_id' => (int) $invoice->id,
            'rule_key' => 'dispatch:legacy',
            'meta' => ['message' => '历史失败'],
            'executed_at' => null,
        ]);

        $this->mock(ReferralService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('rewardForPaidInvoice')->once()->andReturnNull();
        });

        app(InvoicePaidOrchestrator::class)->processPaidInvoiceReferralRewardById((int) $invoice->id, 'trace-ok-1');

        $this->assertSame(
            0,
            AutomationLog::query()
                ->where('task_key', 'referral-reward')
                ->where('action', 'failed')
                ->where('object_id', (int) $invoice->id)
                ->whereNull('executed_at')
                ->count(),
            '成功补偿后不得残留待处理失败记录'
        );
    }

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makePaidInvoice(User $buyer): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $buyer->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => 100.00,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDay(),
            'paid_at' => now(),
        ]);
    }
}
