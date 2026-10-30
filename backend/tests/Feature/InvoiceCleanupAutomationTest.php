<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\Automation\InvoiceCleanupAutomationService;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\MixPaymentService;
use App\Services\Finance\PaymentCallbackProjector;
use App\Services\Finance\TradeLifecycleService;
use App\Services\Order\OrderService;
use App\Services\System\OperationLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * D6A-01/D6A-02 回归：
 * - 开关 pending_order_cleanup_enabled 关闭时清理入口短路，不再自动取消未付账单/订单；
 * - 系统生成的续费账单与影子续费订单按 due_date 计费支付窗口（不再按 5 分钟），
 *   到期前/逾期催收与提醒链路依赖这两条记录存活；
 * - due_date 已过的续费记录仍必须被清理，豁免不能变成永不过期。
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

    public function test_renew_invoice_with_passed_due_date_still_cleaned(): void
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

        // 续费豁免只到 due_date 当日 23:59:59 为止：账期已过的续费账单与其影子订单仍须被清理
        $invoice = $this->createUnpaidInvoice($user, $product, OrderType::RENEW, now()->subDay(), [
            'service_id' => $service->id,
            'billing_cycle' => 'monthly',
            'due_date' => now()->subDay(),
        ]);
        $order = $this->createPendingOrder($user, $product, OrderType::RENEW, now()->subDay(), [
            'service_id' => $service->id,
            'billing_cycle' => 'monthly',
        ]);
        $invoice->forceFill(['order_id' => $order->id])->save();

        app(InvoiceCleanupAutomationService::class)->handle();

        $this->assertSame(InvoiceStatus::CANCELLED, (int) $invoice->fresh()->status, 'due_date 已过的续费账单应被清理');
        $this->assertSame(OrderStatus::CANCELLED, (int) $order->fresh()->status, 'due_date 已过时影子续费订单应随账单取消');
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

        // 独立挂单（无账单）：验证订单侧清理路径本身。账单级联场景下订单会随账单先被取消
        // （CheckoutService::cancel 级联），orders_cancelled 不再计入，不能作为订单侧计数的唯一来源。
        $standaloneOrder = $this->createPendingOrder($user, $product, OrderType::NEW, now()->subMinutes(10));

        $summary = app(InvoiceCleanupAutomationService::class)->handle();

        // 共库并行会残留其他用例的未付数据，计数只做下限断言，状态以本次造数对象为准
        $this->assertGreaterThanOrEqual(1, $summary['invoices_cancelled']);
        $this->assertGreaterThanOrEqual(1, $summary['orders_cancelled'], '独立挂单必须由订单侧清理取消');
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $invoice->fresh()->status);
        $this->assertSame(OrderStatus::CANCELLED, (int) $order->fresh()->status, '关联订单应随账单级联取消');
        $this->assertSame(OrderStatus::CANCELLED, (int) $standaloneOrder->fresh()->status);
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

    /**
     * N3-02-B：挂账订单被豁免支付窗口清理，关联挂账单被管理员取消后订单会永久停在 PENDING。
     * 定时任务在宽限期（7 天）后把这类订单收敛为 CANCELLED，给用户端一个终态。
     */
    public function test_abandoned_admin_manual_order_is_converged_after_grace_period(): void
    {
        $user = User::factory()->create();
        $order = $this->createPendingOrder($user, $this->makeProduct(), OrderType::NEW, now(), [
            'config_snapshot' => ['admin_manual' => true],
        ]);
        // 关联挂账单已被管理员取消，且取消时刻（updated_at 近似）已超过 7 天宽限期
        $invoice = $this->createCancelledInvoice($user, $order, now()->subDays(8));

        $summary = app(InvoiceCleanupAutomationService::class)->handle();

        $this->assertGreaterThanOrEqual(1, $summary['abandoned_orders_cancelled']);
        $this->assertSame(OrderStatus::CANCELLED, (int) $order->fresh()->status, '超期僵尸挂账订单应被收敛');
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $invoice->fresh()->status);
    }

    public function test_abandoned_admin_manual_order_within_grace_period_is_kept(): void
    {
        $user = User::factory()->create();
        $order = $this->createPendingOrder($user, $this->makeProduct(), OrderType::NEW, now(), [
            'config_snapshot' => ['admin_manual' => true],
        ]);
        // 账单刚被取消，仍在宽限期内：给运营留出纠正窗口，不得立刻收敛
        $this->createCancelledInvoice($user, $order, now()->subDays(2));

        app(InvoiceCleanupAutomationService::class)->handle();

        $this->assertSame(OrderStatus::PENDING, (int) $order->fresh()->status, '宽限期内不应收敛');
    }

    /**
     * 收敛只针对挂账订单：普通订单（无 admin_manual 标记）即使关联账单已取消超期，
     * 也不走这条通道——它由支付窗口清理负责，两条通道的判据不能混。
     */
    public function test_convergence_skips_orders_without_admin_manual_mark(): void
    {
        $user = User::factory()->create();
        $order = $this->createPendingOrder($user, $this->makeProduct(), OrderType::NEW, now());
        $this->createCancelledInvoice($user, $order, now()->subDays(8));

        app(InvoiceCleanupAutomationService::class)->handle();

        $this->assertSame(OrderStatus::PENDING, (int) $order->fresh()->status, '非挂账订单不由收敛通道处理');
    }

    /**
     * D-1：单条订单取消失败不得中断整轮清理。
     *
     * 用真实 OrderService 的匿名子类只覆写 cancelExpiredPendingOrder 抛异常，
     * 其余方法（cancel / isAdminManualOrder）保持原实现，确保断言的是清理编排行为
     * 而不是某个 mock 的返回值。
     */
    public function test_order_cancel_failure_does_not_abort_whole_round(): void
    {
        $product = $this->makeProduct();
        $user = User::factory()->create();

        // 一条到期待关闭的订单：取消失败
        $this->createPendingOrder($user, $product, OrderType::NEW, now()->subMinutes(10));

        // 一条超期僵尸挂账订单：在订单清理之后的步骤，用于证明流程没有被中断
        $abandonedOrder = $this->createPendingOrder($user, $product, OrderType::NEW, now(), [
            'config_snapshot' => ['admin_manual' => true],
        ]);
        $this->createCancelledInvoice($user, $abandonedOrder, now()->subDays(8));

        // 用真实子类而不是 mock：清理服务还依赖 OrderService 的 cancel()/isAdminManualOrder() 真实现
        // （Mockery 的 partial mock 不会跑父类构造函数，依赖属性未初始化，会污染后续断言）。
        $failingOrderService = new class(app(MixPaymentService::class), app(CheckoutSecurityService::class), app(OperationLogService::class), app(PaymentCallbackProjector::class), app(TradeLifecycleService::class)) extends OrderService
        {
            public int $failedCalls = 0;

            public function cancelExpiredPendingOrder(Order $order, array $context = []): Order
            {
                $this->failedCalls++;

                throw new BusinessException('当前账单状态不支持取消订单');
            }
        };

        $this->app->instance(OrderService::class, $failingOrderService);

        $summary = app(InvoiceCleanupAutomationService::class)->handle();

        $this->assertGreaterThanOrEqual(1, $failingOrderService->failedCalls, '订单清理应逐条尝试');
        $this->assertSame(0, $summary['orders_cancelled'], '取消失败的订单不计入已取消');
        // 关键断言：失败被逐条兜住后，后面的收敛步骤仍然执行
        $this->assertGreaterThanOrEqual(1, $summary['abandoned_orders_cancelled'], '单条失败不得中断后续收敛');
        $this->assertSame(OrderStatus::CANCELLED, (int) $abandonedOrder->fresh()->status);
    }

    /**
     * D-4：一单多账单时不得误取消仍可支付的账单。
     *
     * invoices.order_id 无唯一约束（实库当前无重复，但约束层不保证）。收敛判据只看
     * 「存在一张已取消且超期的账单」是不够的：OrderService::cancel 会按 order_id 取账单，
     * 并允许取消仍待支付的那张，等于替用户取消掉账期内还能付的账单。
     */
    public function test_convergence_skips_order_with_a_payable_invoice(): void
    {
        $product = $this->makeProduct();
        $user = User::factory()->create();

        $order = $this->createPendingOrder($user, $product, OrderType::NEW, now(), [
            'config_snapshot' => ['admin_manual' => true],
        ]);

        // 一张已取消且超期（满足 whereHas 条件）
        $this->createCancelledInvoice($user, $order, now()->subDays(8));

        // 同一订单再挂一张仍待支付的账单（用户仍可付款）
        $payable = $this->createUnpaidInvoice($user, $product, 'manual', now()->subDays(1), [
            'order_id' => $order->id,
            'config_snapshot' => ['admin_manual' => true],
        ]);

        $summary = app(InvoiceCleanupAutomationService::class)->handle();

        $this->assertSame(0, $summary['abandoned_orders_cancelled'], '存在可支付账单时不得收敛');
        $this->assertSame(OrderStatus::PENDING, (int) $order->fresh()->status);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $payable->fresh()->status, '可支付账单不得被级联取消');
    }

    /**
     * D-2：关闭「未付款账单清理」开关只停未付款单据的自动取消，
     * 不应连带停掉僵尸挂账订单的终态收敛（否则关开关即复发）。
     */
    public function test_abandoned_convergence_runs_even_when_cleanup_switch_disabled(): void
    {
        Setting::setValue('automation', 'pending_order_cleanup_enabled', '0');

        $product = $this->makeProduct();
        $user = User::factory()->create();

        $unpaidInvoice = $this->createUnpaidInvoice($user, $product, 'normal', now()->subMinutes(10));
        $pendingOrder = $this->createPendingOrder($user, $product, OrderType::NEW, now()->subMinutes(10));

        $abandonedOrder = $this->createPendingOrder($user, $product, OrderType::NEW, now(), [
            'config_snapshot' => ['admin_manual' => true],
        ]);
        $this->createCancelledInvoice($user, $abandonedOrder, now()->subDays(8));

        $summary = app(InvoiceCleanupAutomationService::class)->handle();

        $this->assertTrue((bool) ($summary['pending_order_cleanup_skipped'] ?? false));
        $this->assertSame(InvoiceStatus::UNPAID, (int) $unpaidInvoice->fresh()->status, '开关关闭时不取消未付款账单');
        $this->assertSame(OrderStatus::PENDING, (int) $pendingOrder->fresh()->status, '开关关闭时不取消未付款订单');
        $this->assertGreaterThanOrEqual(1, $summary['abandoned_orders_cancelled'], '僵尸挂账收敛不受该开关约束');
        $this->assertSame(OrderStatus::CANCELLED, (int) $abandonedOrder->fresh()->status);
    }

    private function makeProduct(): Product
    {
        return Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
    }

    private function createCancelledInvoice(User $user, Order $order, $cancelledAt): Invoice
    {
        $invoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'order_id' => $order->id,
            'product_id' => $order->product_id,
            'type' => 'manual',
            'amount' => 35.00,
            'status' => InvoiceStatus::CANCELLED,
            'due_date' => now()->addDays(7),
            'config_snapshot' => ['admin_manual' => true],
        ]);
        // 账单表无 cancelled_at：取消必然 save() 刷新 updated_at，用它近似取消时刻
        $invoice->updated_at = $cancelledAt;
        $invoice->save();

        return $invoice;
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
