<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Jobs\ProcessPaidOrderFulfillmentJob;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
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

        $snapshot = (array) ($invoice->fresh()->config_snapshot ?? []);
        $this->assertTrue((bool) ($snapshot['requires_refund'] ?? false));
    }

    public function test_failed_skips_renew_refunded_and_unpaid_scenarios(): void
    {
        // 续费订单：有自己的恢复与退款链路，不在本 Job 打标
        [$renewOrder, $renewInvoice] = $this->makePaidUnfulfilledStack('renew');
        $this->runFailedHook((int) $renewOrder->id);
        $this->assertFalse((bool) (($renewInvoice->fresh()->config_snapshot ?? [])['requires_refund'] ?? false));

        // 已退款订单：资金已退，无悬挂
        [$refundedOrder, $refundedInvoice] = $this->makePaidUnfulfilledStack();
        $refundedOrder->forceFill(['status' => OrderStatus::REFUNDED])->save();
        $this->runFailedHook((int) $refundedOrder->id);
        $this->assertFalse((bool) (($refundedInvoice->fresh()->config_snapshot ?? [])['requires_refund'] ?? false));

        // 未支付账单：没有资金悬挂
        [$unpaidOrder, $unpaidInvoice] = $this->makePaidUnfulfilledStack('new', InvoiceStatus::UNPAID);
        $this->runFailedHook((int) $unpaidOrder->id);
        $this->assertFalse((bool) (($unpaidInvoice->fresh()->config_snapshot ?? [])['requires_refund'] ?? false));
    }

    public function test_failed_is_idempotent_and_preserves_snapshot(): void
    {
        [$order, $invoice] = $this->makePaidUnfulfilledStack();

        $this->runFailedHook((int) $order->id);
        $this->runFailedHook((int) $order->id);

        $snapshot = (array) ($invoice->fresh()->config_snapshot ?? []);
        $this->assertTrue((bool) ($snapshot['requires_refund'] ?? false));
        // 原有快照内容不被覆盖
        $this->assertSame('basic', (string) ($snapshot['plan'] ?? ''));
    }

    /**
     * 直接触发失败钩子：failed() 在重试耗尽后由队列框架调用，这里等价模拟。
     */
    private function runFailedHook(int $orderId): void
    {
        (new ProcessPaidOrderFulfillmentJob($orderId))->failed(new \RuntimeException('履约失败模拟'));
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
            'config_snapshot' => ['plan' => 'basic', 'fulfillment_pending' => true],
        ]);

        return [$order->refresh(), $invoice];
    }
}
