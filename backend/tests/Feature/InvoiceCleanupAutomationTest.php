<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\Automation\InvoiceCleanupAutomationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * D6A-01/D6A-02 回归：
 * - 开关 pending_order_cleanup_enabled 关闭时清理入口短路，不再自动取消未付账单/订单；
 * - 系统生成的续费账单与影子续费订单豁免 5 分钟支付会话窗口
 *   （到期前/逾期催收与提醒链路依赖这两条记录存活）。
 */
class InvoiceCleanupAutomationTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        // 恢复开关默认值，避免污染同库并行的其他用例
        Setting::setValue('automation', 'pending_order_cleanup_enabled', '1');
        Setting::setValue('automation', 'pending_recharge_cleanup_enabled', '1');

        parent::tearDown();
    }

    public function test_disabled_switch_skips_invoice_and_order_cleanup(): void
    {
        Setting::setValue('automation', 'pending_order_cleanup_enabled', '0');

        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $user = User::factory()->create();
        $invoice = $this->createUnpaidInvoice($user, $product, 'normal', now()->subMinutes(10));
        $order = $this->createPendingOrder($user, $product, OrderType::NEW, now()->subMinutes(10));

        $summary = app(InvoiceCleanupAutomationService::class)->handle();

        $this->assertSame(0, $summary['invoices_cancelled']);
        $this->assertSame(0, $summary['orders_cancelled']);
        $this->assertTrue((bool) ($summary['pending_order_cleanup_skipped'] ?? false));
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, (int) $order->fresh()->status);
    }

    public function test_renew_invoice_and_shadow_order_exempt_from_payment_window(): void
    {
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $user = User::factory()->create();
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
        ]);

        // 超过 5 分钟支付会话窗口的续费账单与影子订单：D6A-01 修复前创建即被取消
        $invoice = $this->createUnpaidInvoice($user, $product, OrderType::RENEW, now()->subMinutes(10), [
            'service_id' => $service->id,
            'billing_cycle' => 'monthly',
        ]);
        $order = $this->createPendingOrder($user, $product, OrderType::RENEW, now()->subMinutes(10), [
            'service_id' => $service->id,
            'billing_cycle' => 'monthly',
        ]);
        $invoice->forceFill(['order_id' => $order->id])->save();

        $summary = app(InvoiceCleanupAutomationService::class)->handle();

        // 共库并行会残留其他用例的未付数据，计数不做绝对断言，只断言本次造数对象存活
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, (int) $order->fresh()->status);
    }

    public function test_normal_invoice_and_order_cancelled_after_payment_window(): void
    {
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $user = User::factory()->create();

        // 购物车支付会话型账单/订单：超时仍应被清理
        $invoice = $this->createUnpaidInvoice($user, $product, 'normal', now()->subMinutes(10));
        $order = $this->createPendingOrder($user, $product, OrderType::NEW, now()->subMinutes(10));
        $invoice->forceFill(['order_id' => $order->id])->save();

        $summary = app(InvoiceCleanupAutomationService::class)->handle();

        // 共库并行会残留其他用例的未付数据，计数只做下限断言，状态以本次造数对象为准
        $this->assertGreaterThanOrEqual(1, $summary['invoices_cancelled']);
        $this->assertGreaterThanOrEqual(1, $summary['orders_cancelled']);
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $invoice->fresh()->status);
        $this->assertSame(OrderStatus::CANCELLED, (int) $order->fresh()->status);
    }

    public function test_disabled_recharge_switch_skips_recharge_cleanup(): void
    {
        Setting::setValue('automation', 'pending_recharge_cleanup_enabled', '0');

        $user = User::factory()->create();
        $payment = Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => $user->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 30.00,
            'currency' => 'CNY',
            'status' => PaymentStatus::PENDING,
        ]);
        $payment->created_at = now()->subHours(2);
        $payment->save();

        $summary = app(InvoiceCleanupAutomationService::class)->handle();

        $this->assertSame(0, $summary['recharges_expired']);
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->fresh()->status);
    }

    private function createUnpaidInvoice(User $user, Product $product, string $type, $createdAt, array $extra = []): Invoice
    {
        $invoice = Invoice::query()->create(array_merge([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => $type,
            'amount' => 35.00,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(7),
        ], $extra));
        $invoice->created_at = $createdAt;
        $invoice->save();

        return $invoice;
    }

    private function createPendingOrder(User $user, Product $product, string $type, $createdAt, array $extra = []): Order
    {
        $order = Order::query()->create(array_merge([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => $type,
            'amount' => 35.00,
            'quantity' => 1,
            'status' => OrderStatus::PENDING,
        ], $extra));
        $order->created_at = $createdAt;
        $order->save();

        return $order;
    }
}
