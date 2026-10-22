<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Jobs\ProcessPaidOrderFulfillmentJob;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 开通队列重试耗尽后的资金安全标记：已支付未履约的新购账单必须被打上
 * requires_refund 标记（config_snapshot 内），避免用户资金悬挂在无人认领的
 * 已付订单上；续费/升级与已退款/未支付场景不在此打标，重复调用幂等。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class OrderFulfillmentRefundFlagTest extends TestCase
{
    use DatabaseTransactions;

    public function test_failed_marks_paid_unfulfilled_new_order_invoice(): void
    {
        [$order, $invoice] = $this->makePaidUnfulfilledStack();

        $this->runFailedHook((int) $order->id);

        $this->assertTrue((bool) $invoice->fresh()->requires_refund);
    }

    public function test_failed_skips_renew_refunded_and_unpaid_scenarios(): void
    {
        // 续费订单：有自己的恢复与退款链路，不在本 Job 打标
        [$renewOrder, $renewInvoice] = $this->makePaidUnfulfilledStack('renew');
        $this->runFailedHook((int) $renewOrder->id);
        $this->assertFalse((bool) $renewInvoice->fresh()->requires_refund);

        // 已退款订单：资金已退，无悬挂
        [$refundedOrder, $refundedInvoice] = $this->makePaidUnfulfilledStack();
        $refundedOrder->forceFill(['status' => OrderStatus::REFUNDED])->save();
        $this->runFailedHook((int) $refundedOrder->id);
        $this->assertFalse((bool) $refundedInvoice->fresh()->requires_refund);

        // 未支付账单：没有资金悬挂
        [$unpaidOrder, $unpaidInvoice] = $this->makePaidUnfulfilledStack('new', InvoiceStatus::UNPAID);
        $this->runFailedHook((int) $unpaidOrder->id);
        $this->assertFalse((bool) $unpaidInvoice->fresh()->requires_refund);
    }

    public function test_failed_is_idempotent_and_preserves_snapshot(): void
    {
        [$order, $invoice] = $this->makePaidUnfulfilledStack();

        $this->runFailedHook((int) $order->id);
        $this->runFailedHook((int) $order->id);

        $this->assertTrue((bool) $invoice->fresh()->requires_refund);
        // 原有快照内容不被覆盖
        $snapshot = (array) ($invoice->fresh()->config_snapshot ?? []);
        $this->assertSame('basic', (string) ($snapshot['plan'] ?? ''));
    }

    public function test_sync_first_failure_does_not_mark_requires_refund(): void
    {
        // dispatchSync 第一次失败就会调用 failed()，但这不是「重试耗尽」：
        // 后续还有 provision:retry-failed 巡检兜底，直接打退款标记属于误判。
        [$order, $invoice] = $this->makePaidUnfulfilledStack();

        $this->runFailedHook((int) $order->id, 'sync', 1);

        $this->assertFalse((bool) $invoice->fresh()->requires_refund);

        // 真跑 sync 队列：异常冒泡但账单不被打标
        config(['queue.default' => 'sync']);
        try {
            ProcessPaidOrderFulfillmentJob::dispatchSync((int) $order->id);
        } catch (\Throwable) {
            // 履约失败在 sync 驱动下会抛回调用方，这里只关心打标行为
        }

        $this->assertFalse((bool) $invoice->fresh()->requires_refund);
    }

    public function test_non_final_attempt_does_not_mark_requires_refund(): void
    {
        // 队列驱动每次失败都会调用 failed()：第 1、2 次失败仍会重试，不能打标。
        [$order, $invoice] = $this->makePaidUnfulfilledStack();

        $this->runFailedHook((int) $order->id, 'redis', 1);
        $this->assertFalse((bool) $invoice->fresh()->requires_refund);

        $this->runFailedHook((int) $order->id, 'redis', 2);
        $this->assertFalse((bool) $invoice->fresh()->requires_refund);

        // 第 3 次 = tries 用尽，此时才允许打标
        $this->runFailedHook((int) $order->id, 'redis', 3);
        $this->assertTrue((bool) $invoice->fresh()->requires_refund);
    }

    public function test_stale_pending_invoice_is_marked_by_provision_retry_failed(): void
    {
        // 开通改同步/响应后执行后，队列 failed() 钩子不再触发；
        // provision:retry-failed 以 fulfillment_pending 停留时长兜底判定"履约已放弃"。
        [$order, $invoice] = $this->makePaidUnfulfilledStack();
        $invoice->forceFill([
            'fulfillment_pending' => true,
            'fulfillment_pending_at' => now()->subHours(2),
        ])->save();

        $this->artisan('provision:retry-failed')->assertExitCode(0);

        $this->assertTrue((bool) $invoice->fresh()->requires_refund);
    }

    public function test_recent_pending_invoice_is_not_marked(): void
    {
        [$order, $invoice] = $this->makePaidUnfulfilledStack();
        $invoice->forceFill([
            'fulfillment_pending' => true,
            'fulfillment_pending_at' => now()->subMinutes(5),
        ])->save();

        $this->artisan('provision:retry-failed')->assertExitCode(0);

        $this->assertFalse((bool) $invoice->fresh()->requires_refund);
    }

    public function test_pending_without_timestamp_is_not_marked(): void
    {
        // 迁移后 fulfillment_pending_at 缺失的异常行不做超时判定：
        // 宁可漏标待人工核对，也不能凭 updated_at 误判正在履约的账单。
        [$order, $invoice] = $this->makePaidUnfulfilledStack();
        $invoice->forceFill(['fulfillment_pending' => true, 'fulfillment_pending_at' => null])->save();
        Invoice::query()->whereKey((int) $invoice->id)->update(['updated_at' => now()->subHours(3)]);

        $this->artisan('provision:retry-failed')->assertExitCode(0);

        $this->assertFalse((bool) $invoice->fresh()->requires_refund);
    }

    /**
     * 直接触发失败钩子：failed() 由队列框架在每次失败时调用，不一定代表重试耗尽。
     * 这里用假 job 明确给出「哪条连接、第几次尝试」，覆盖「耗尽才打标」的判据。
     */
    private function runFailedHook(int $orderId, string $connection = 'redis', int $attempts = 3): void
    {
        $job = new ProcessPaidOrderFulfillmentJob($orderId);
        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn($attempts);
        $queueJob->shouldReceive('getConnectionName')->andReturn($connection);
        $job->job = $queueJob;

        $job->failed(new \RuntimeException('履约失败模拟'));
    }

    private function makePaidUnfulfilledStack(
        string $orderType = 'new',
        int $invoiceStatus = InvoiceStatus::PAID,
    ): array {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => "refund-flag-{$suffix}@example.test",
            'password' => 'Temp@123456',
            'nickname' => 'refund-flag-'.$suffix,
        ]);

        $order = Order::query()->create([
            'order_no' => 'RFNO'.$suffix,
            'user_id' => (int) $user->id,
            'type' => $orderType,
            'status' => OrderStatus::PAID,
            'amount' => '88.00',
            'paid_amount' => '88.00',
            'paid_at' => now(),
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'RF'.$suffix,
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => $orderType,
            'amount' => '88.00',
            'paid_amount' => $invoiceStatus === InvoiceStatus::PAID ? '88.00' : '0.00',
            'status' => $invoiceStatus,
            'paid_at' => $invoiceStatus === InvoiceStatus::PAID ? now() : null,
            'config_snapshot' => ['plan' => 'basic'],
            'fulfillment_pending' => true,
            'fulfillment_pending_at' => now(),
        ]);

        return [$order->refresh(), $invoice];
    }
}
