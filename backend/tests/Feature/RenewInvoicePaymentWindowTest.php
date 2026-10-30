<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\ServiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\Automation\InvoiceCleanupAutomationService;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Order\OrderService;
use App\Services\Provisioning\ServiceRenewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 续费支付窗口回归（D6A-01 之后的补齐）：
 * 续费账单与其影子续费订单要承载「到期前 1/3/5 天提醒 + 逾期催收」，
 * 若沿用 5 分钟支付会话窗口，请求路径懒清理（账单列表/详情、订单列表）
 * 与定时清理都会把它们当过期单取消，提醒落地页一打开就是"已取消"，续费链路直接断。
 *
 * 修复后口径：
 * - 续费记录（Invoices.type = renew 及其影子订单）按 due_date 当日 23:59:59 计费窗口；
 * - 其余记录仍按 created_at + 5 分钟；
 * - 定时清理服务不再自己判 RENEW 豁免，统一走 CheckoutSecurityService 判据。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class RenewInvoicePaymentWindowTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 判据本身：续费按 due_date 计费，普通账单按 5 分钟。
     */
    public function test_renew_invoice_payment_window_follows_due_date(): void
    {
        [$user, $service, $product] = $this->renewFixture();
        $security = app(CheckoutSecurityService::class);

        $renewInvoice = $this->makeInvoice($user, $product, $service, [
            'created_at' => now()->subMinutes(10),
            'due_date' => now()->addDays(7),
        ]);

        $this->assertFalse($security->isPaymentSessionExpired($renewInvoice), '续费账单 7 天账期内不得判定为支付窗口过期');
        $this->assertSame(
            $renewInvoice->due_date->copy()->endOfDay()->timestamp,
            $security->paymentSessionExpiresAt($renewInvoice)->timestamp,
            '续费账单支付窗口应截止到 due_date 当日 23:59:59'
        );

        // 影子订单表没有 due_date，必须跟随关联账单的窗口
        $shadowOrder = $this->makeOrder($user, $product, $service, ['created_at' => now()->subMinutes(10)]);
        $renewInvoice->forceFill(['order_id' => $shadowOrder->id])->save();
        $this->assertFalse($security->isPaymentSessionExpired($shadowOrder->fresh()), '影子续费订单应跟随账单 due_date 窗口');

        // due_date 已过：账单与影子订单同时过期，等待清理
        $renewInvoice->forceFill(['due_date' => now()->subDay()])->save();
        $this->assertTrue($security->isPaymentSessionExpired($renewInvoice->fresh()), 'due_date 已过的续费账单应判定为过期');
        $this->assertTrue($security->isPaymentSessionExpired($shadowOrder->fresh()), 'due_date 已过时影子订单也应判定为过期');

        // 非续费账单不受 due_date 影响，仍按 5 分钟窗口（回归护栏）
        $normalInvoice = $this->makeInvoice($user, $product, $service, [
            'type' => 'new',
            'created_at' => now()->subMinutes(10),
            'due_date' => now()->addDays(7),
        ]);
        $this->assertTrue($security->isPaymentSessionExpired($normalInvoice), '普通账单仍应按 created_at + 5 分钟判定过期');
    }

    /**
     * 请求路径：超过 5 分钟仍在账期内的续费账单，详情页必须仍可支付且下发支付会话令牌。
     */
    public function test_request_path_keeps_renew_invoice_payable_beyond_payment_session(): void
    {
        [$user, $service, $product] = $this->renewFixture();
        $invoice = $this->makeInvoice($user, $product, $service, [
            'created_at' => now()->subMinutes(10),
            'due_date' => now()->addDays(7),
        ]);
        $order = $this->makeOrder($user, $product, $service, ['created_at' => now()->subMinutes(10)]);
        $invoice->forceFill(['order_id' => $order->id])->save();

        Sanctum::actingAs($user);
        $this->forgetExpiredSweepThrottle((int) $user->id);

        $detail = $this->getJson('/api/v2/client/invoices/'.$invoice->id)->assertOk();

        $this->assertSame(InvoiceStatus::UNPAID, (int) $detail->json('data.invoice.basic.status'), '请求路径不得取消未过账期的续费账单');
        $this->assertTrue((bool) $detail->json('data.invoice.payment_options.payment_security.can_pay'), '未过账期的续费账单应可支付');
        $this->assertNotSame('', (string) $detail->json('data.invoice.payment_options.payment_security.session_token'), '未过账期的续费账单应下发支付会话令牌');

        // 账单列表路径同样不得清掉续费账单
        $this->forgetExpiredSweepThrottle((int) $user->id);
        $this->getJson('/api/v2/client/invoices?page=1&page_size=20')->assertOk();
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->fresh()->status, '账单列表懒清理不得取消未过账期的续费账单');

        // 订单列表路径不得清掉影子续费订单
        $this->getJson('/api/v2/client/orders?page=1&page_size=20')->assertOk();
        $this->assertSame(OrderStatus::PENDING, (int) $order->fresh()->status, '订单列表懒清理不得取消未过账期的影子续费订单');
    }

    /**
     * 回归护栏：普通账单超过 5 分钟仍必须被请求路径清掉，避免修复把购物车脏单永久留下。
     */
    public function test_request_path_still_cancels_normal_invoice_after_payment_session(): void
    {
        [$user, $service, $product] = $this->renewFixture();
        $invoice = $this->makeInvoice($user, $product, $service, [
            'type' => 'new',
            'created_at' => now()->subMinutes(10),
            'due_date' => now()->addDays(7),
        ]);

        Sanctum::actingAs($user);
        $this->forgetExpiredSweepThrottle((int) $user->id);

        $detail = $this->getJson('/api/v2/client/invoices/'.$invoice->id)->assertOk();

        $this->assertSame(InvoiceStatus::CANCELLED, (int) $detail->json('data.invoice.basic.status'), '普通账单超过 5 分钟仍应被取消');
        $this->assertFalse((bool) $detail->json('data.invoice.payment_options.payment_security.can_pay'), '已取消账单不可支付');
    }

    /**
     * 复用口径：同周期未付但支付窗口已过的续费账单不再复用，改由建单事务取消并重建，
     * 避免每次都把用户送到同一张无可用支付方式的死单。
     */
    public function test_expired_unpaid_renew_invoice_is_replaced_instead_of_reused(): void
    {
        [$user, $service] = $this->renewFixture();
        $renewService = app(ServiceRenewService::class);

        $first = $renewService->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');
        $this->assertSame(InvoiceStatus::UNPAID, (int) $first->status);

        // 把首张账单推到支付窗口之外（due_date 已过但仍未支付）
        $first->forceFill(['created_at' => now()->subMinutes(10), 'due_date' => now()->subDay()])->save();

        $second = $renewService->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');

        $this->assertNotSame((int) $first->id, (int) $second->id, '支付窗口已过的续费账单不得复用');
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $first->fresh()->status, '过期的同周期续费账单应被取消');
        $this->assertSame(InvoiceStatus::UNPAID, (int) $second->status, '新建的续费账单应为待支付');
        $this->assertSame(
            1,
            Invoice::query()
                ->where('service_id', (int) $service->id)
                ->where('type', OrderType::RENEW)
                ->where('status', InvoiceStatus::UNPAID)
                ->count(),
            '同周期只应保留一张待支付续费账单'
        );
    }

    /**
     * 豁免只到账期为止：due_date 已过的续费账单与影子续费订单，请求路径懒清理必须照常关闭，
     * 否则过期续费单会永久占住「待支付」入口。
     */
    public function test_request_path_cancels_expired_renew_invoice_and_shadow_order(): void
    {
        [$user, $service, $product] = $this->renewFixture();

        $order = $this->makeOrder($user, $product, $service, ['created_at' => now()->subDays(2)]);
        $invoice = $this->makeInvoice($user, $product, $service, [
            'created_at' => now()->subDays(2),
            'due_date' => now()->subDay(),
            'order_id' => (int) $order->id,
        ]);

        Sanctum::actingAs($user);
        $this->forgetExpiredSweepThrottle((int) $user->id);

        $detail = $this->getJson('/api/v2/client/invoices/'.$invoice->id)->assertOk();

        $this->assertSame(InvoiceStatus::CANCELLED, (int) $detail->json('data.invoice.basic.status'), 'due_date 已过的续费账单应被请求路径取消');
        $this->assertFalse((bool) $detail->json('data.invoice.payment_options.payment_security.can_pay'), '已取消的续费账单不可支付');
        $this->assertSame(OrderStatus::CANCELLED, (int) $order->fresh()->status, '影子续费订单应随账单一起取消');
    }

    /**
     * 订单列表路径同样要收口只挂账单、没有联表可查的存量影子订单。
     */
    public function test_order_list_path_cancels_expired_renew_shadow_order(): void
    {
        [$user, $service, $product] = $this->renewFixture();

        $order = $this->makeOrder($user, $product, $service, ['created_at' => now()->subDays(2)]);
        $this->makeInvoice($user, $product, $service, [
            'created_at' => now()->subDays(2),
            'due_date' => now()->subDay(),
            'order_id' => (int) $order->id,
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v2/client/orders?page=1&page_size=20')->assertOk();

        $this->assertSame(OrderStatus::CANCELLED, (int) $order->fresh()->status, '账期已过的影子续费订单应被订单列表取消');

        // 反向保障：账期内的影子续费订单不得被订单列表取消
        $active = $this->makeOrder($user, $product, $service, ['created_at' => now()->subDays(2)]);
        $this->makeInvoice($user, $product, $service, [
            'created_at' => now()->subDays(2),
            'due_date' => now()->addDays(7),
            'order_id' => (int) $active->id,
        ]);

        $this->getJson('/api/v2/client/orders?page=1&page_size=20')->assertOk();
        $this->assertSame(OrderStatus::PENDING, (int) $active->fresh()->status, '账期内的影子续费订单不得被订单列表取消');
    }

    /**
     * 管理员挂账账单与续费账单一样按账期收款：支付窗口跟随 due_date，
     * 且请求路径（详情/列表）与定时清理都不得把它关闭（关掉等于用户彻底失去支付入口）。
     */
    public function test_admin_manual_invoice_window_follows_due_date_and_survives_cleanup(): void
    {
        [$user, $service, $product] = $this->renewFixture();

        $manual = $this->makeInvoice($user, $product, $service, [
            'type' => InvoiceType::MANUAL,
            'created_at' => now()->subMinutes(30),
            'due_date' => now()->addDays(7),
            'config_snapshot' => ['admin_manual' => true],
        ]);

        $this->assertFalse(
            app(CheckoutSecurityService::class)->isPaymentSessionExpired($manual),
            '挂账账单在账期内不得判定为支付窗口过期'
        );

        Sanctum::actingAs($user);
        $this->forgetExpiredSweepThrottle((int) $user->id);

        $detail = $this->getJson('/api/v2/client/invoices/'.$manual->id)->assertOk();
        $this->assertSame(InvoiceStatus::UNPAID, (int) $detail->json('data.invoice.basic.status'), '请求路径不得取消挂账账单');
        $this->assertTrue((bool) $detail->json('data.invoice.payment_options.payment_security.can_pay'), '账期内挂账账单应可支付');
        $this->assertNotSame('', (string) $detail->json('data.invoice.payment_options.payment_security.session_token'), '账期内挂账账单应能签发支付会话');

        Setting::setValue('automation', 'pending_order_cleanup_enabled', '1');

        $this->forgetExpiredSweepThrottle((int) $user->id);
        $this->getJson('/api/v2/client/invoices?page=1&page_size=20')->assertOk();
        $this->assertSame(InvoiceStatus::UNPAID, (int) $manual->fresh()->status, '账单列表懒清理不得取消挂账账单');

        app(InvoiceCleanupAutomationService::class)->handle();
        $this->assertSame(InvoiceStatus::UNPAID, (int) $manual->fresh()->status, '定时清理不得取消挂账账单');
    }

    /**
     * 账期内的续费账单必须复用（同一 invoice_id），否则用户会拿到多张并行待支付账单。
     */
    public function test_renew_invoice_within_window_is_reused(): void
    {
        [$user, $service] = $this->renewFixture();
        $renewService = app(ServiceRenewService::class);

        $first = $renewService->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');
        // created_at 已超 5 分钟但 due_date 未过：按 due_date 计窗口，仍应复用
        $first->forceFill(['created_at' => now()->subMinutes(30)])->save();

        $second = $renewService->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');

        $this->assertSame((int) $first->id, (int) $second->id, '账期内续费应复用同一张待支付账单');
        $this->assertSame(InvoiceStatus::UNPAID, (int) $first->fresh()->status);
    }

    /**
     * 列表接口要下发 payment_window_expired，前端才能在列表上区分「可支付 / 窗口已关闭」。
     */
    public function test_invoice_list_exposes_payment_window_expired_flag(): void
    {
        [$user, $service, $product] = $this->renewFixture();

        $renewable = $this->makeInvoice($user, $product, $service, [
            'created_at' => now()->subMinutes(30),
            'due_date' => now()->addDays(7),
        ]);
        // 逾期挂账账单：窗口已过但不会被清理（admin_manual 豁免），列表必须能标记出来
        $overdueManual = $this->makeInvoice($user, $product, $service, [
            'type' => InvoiceType::MANUAL,
            'created_at' => now()->subDays(2),
            'due_date' => now()->subDay(),
            'config_snapshot' => ['admin_manual' => true],
        ]);

        Sanctum::actingAs($user);
        $this->forgetExpiredSweepThrottle((int) $user->id);

        $rows = $this->getJson('/api/v2/client/invoices?page=1&page_size=50')->assertOk()->json('data.list');
        $byId = collect($rows)->keyBy('id');

        $renewableRow = $byId[$renewable->id] ?? null;
        $overdueRow = $byId[$overdueManual->id] ?? null;
        $this->assertIsArray($renewableRow);
        $this->assertIsArray($overdueRow);
        $this->assertArrayHasKey('payment_window_expired', $renewableRow, '列表接口必须下发 payment_window_expired');
        $this->assertArrayHasKey('payment_window_expired', $overdueRow, '列表接口必须下发 payment_window_expired');

        $this->assertFalse((bool) $renewableRow['payment_window_expired'], '账期内续费账单不得标记为支付窗口已关闭');
        $this->assertTrue((bool) $overdueRow['payment_window_expired'], '逾期挂账账单应标记为支付窗口已关闭');
        $this->assertSame(InvoiceStatus::UNPAID, (int) $overdueRow['status'], '逾期挂账账单应仍保持待支付（不被清理）');
    }

    /**
     * 订单侧懒清理幂等：同一条已取消的订单不会被反复计入取消数。
     */
    public function test_order_expired_sweep_is_idempotent(): void
    {
        [$user, $service, $product] = $this->renewFixture();

        $order = $this->makeOrder($user, $product, $service, ['created_at' => now()->subMinutes(30)]);
        $orderService = app(OrderService::class);

        $this->assertSame(1, $orderService->cancelExpiredPendingOrdersForUser((int) $user->id));
        $this->assertSame(OrderStatus::CANCELLED, (int) $order->fresh()->status);
        $this->assertSame(0, $orderService->cancelExpiredPendingOrdersForUser((int) $user->id), '重复清理不应重复计数');
    }

    /**
     * 订单详情请求路径（GET /api/v2/client/orders/{id}）的单条懒清理同样要豁免挂账订单：
     * 管理端"只建订单不建账单"时订单没有可联表的账单，窗口会回落到 5 分钟，
     * 若此处不豁免，用户打开订单详情就会把挂账订单取消掉（进而失去支付入口）。
     */
    public function test_order_detail_path_keeps_admin_manual_order_without_invoice(): void
    {
        [$user, $service, $product] = $this->renewFixture();

        $order = $this->makeOrder($user, $product, $service, [
            'type' => OrderType::NEW,
            'created_at' => now()->subHours(2),
            'config_snapshot' => ['admin_manual' => true],
        ]);

        Sanctum::actingAs($user);

        $detail = $this->getJson('/api/v2/client/orders/'.$order->id)->assertOk();

        $this->assertSame(OrderStatus::PENDING, (int) $detail->json('data.status'), '挂账订单不得被订单详情请求路径取消');
        $this->assertSame(OrderStatus::PENDING, (int) $order->fresh()->status, '挂账订单在库中也应保持待支付');
    }

    /**
     * 挂账订单 + 挂账账单且账期已过：订单详情路径也不得取消订单——
     * OrderService::cancel 会级联取消关联账单，用户将彻底失去支付入口。
     */
    public function test_order_detail_path_keeps_overdue_admin_manual_order_and_invoice(): void
    {
        [$user, $service, $product] = $this->renewFixture();

        $order = $this->makeOrder($user, $product, $service, [
            'type' => OrderType::NEW,
            'created_at' => now()->subDays(2),
            'config_snapshot' => ['admin_manual' => true],
        ]);
        $invoice = $this->makeInvoice($user, $product, $service, [
            'type' => InvoiceType::MANUAL,
            'created_at' => now()->subDays(2),
            'due_date' => now()->subDay(),
            'order_id' => (int) $order->id,
            'config_snapshot' => ['admin_manual' => true],
        ]);

        Sanctum::actingAs($user);

        $detail = $this->getJson('/api/v2/client/orders/'.$order->id)->assertOk();

        $this->assertSame(OrderStatus::PENDING, (int) $detail->json('data.status'), '逾期挂账订单也不得被订单详情取消');
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->fresh()->status, '挂账账单不得被订单取消级联关闭');
        $this->assertTrue((bool) $detail->json('data.invoice.payment_window_expired'), '逾期挂账账单应标记支付窗口已关闭');
    }

    /**
     * 回归护栏：普通待支付订单超过 5 分钟，订单详情路径仍必须取消，避免修复把脏单永久留下。
     */
    public function test_order_detail_path_still_cancels_normal_pending_order(): void
    {
        [$user, $service, $product] = $this->renewFixture();

        $order = $this->makeOrder($user, $product, $service, [
            'type' => OrderType::NEW,
            'created_at' => now()->subHours(2),
        ]);

        Sanctum::actingAs($user);

        $detail = $this->getJson('/api/v2/client/orders/'.$order->id)->assertOk();

        $this->assertSame(OrderStatus::CANCELLED, (int) $detail->json('data.status'), '普通待支付订单超过 5 分钟仍应被订单详情取消');
    }

    /**
     * 订单列表与详情都要下发账单的支付窗口标记，前端订单页才能与账单列表口径一致地收敛支付入口。
     */
    public function test_order_resources_expose_payment_window_expired_flag(): void
    {
        [$user, $service, $product] = $this->renewFixture();

        $renewOrder = $this->makeOrder($user, $product, $service, ['created_at' => now()->subMinutes(30)]);
        $this->makeInvoice($user, $product, $service, [
            'created_at' => now()->subMinutes(30),
            'due_date' => now()->addDays(7),
            'order_id' => (int) $renewOrder->id,
        ]);

        $manualOrder = $this->makeOrder($user, $product, $service, [
            'type' => OrderType::NEW,
            'created_at' => now()->subDays(2),
            'config_snapshot' => ['admin_manual' => true],
        ]);
        $this->makeInvoice($user, $product, $service, [
            'type' => InvoiceType::MANUAL,
            'created_at' => now()->subDays(2),
            'due_date' => now()->subDay(),
            'order_id' => (int) $manualOrder->id,
            'config_snapshot' => ['admin_manual' => true],
        ]);

        Sanctum::actingAs($user);
        $this->forgetExpiredSweepThrottle((int) $user->id);

        $rows = collect($this->getJson('/api/v2/client/orders?page=1&page_size=50')->assertOk()->json('data.list'))->keyBy('id');
        $renewRow = $rows[$renewOrder->id] ?? null;
        $manualRow = $rows[$manualOrder->id] ?? null;

        $this->assertIsArray($renewRow);
        $this->assertIsArray($manualRow);
        $this->assertIsArray($renewRow['invoice'] ?? null, '订单列表必须下发关联账单信息');
        $this->assertArrayHasKey('payment_window_expired', (array) $renewRow['invoice'], '订单列表必须下发账单支付窗口标记');
        $this->assertFalse((bool) $renewRow['invoice']['payment_window_expired'], '账期内续费订单的账单窗口不得标记为已关闭');
        $this->assertTrue((bool) $manualRow['invoice']['payment_window_expired'], '逾期挂账账单应标记为支付窗口已关闭');

        $manualDetail = $this->getJson('/api/v2/client/orders/'.$manualOrder->id)->assertOk();
        $this->assertTrue((bool) $manualDetail->json('data.invoice.payment_window_expired'), '订单详情也要下发账单支付窗口标记');
    }

    /**
     * 前端订单页判据依赖订单资源里的账单 id 与账单状态：
     * 没有关联账单（管理端只建订单不建账单）或账单已非待支付时，订单资源必须如实下发，
     * 由前端据此收掉「去支付」入口，而不是拼出 /client/invoices/undefined/pay。
     */
    public function test_order_resources_expose_invoice_identity_and_status(): void
    {
        [$user, $service, $product] = $this->renewFixture();

        $orderWithoutInvoice = $this->makeOrder($user, $product, $service, [
            'type' => OrderType::NEW,
            'created_at' => now()->subMinutes(30),
            'config_snapshot' => ['admin_manual' => true],
        ]);

        // 续费账单被取消后重建的旧账单：影子订单仍是待支付，但该账单已不可支付
        $cancelledInvoiceOrder = $this->makeOrder($user, $product, $service, ['created_at' => now()->subMinutes(30)]);
        $cancelledInvoice = $this->makeInvoice($user, $product, $service, [
            'created_at' => now()->subMinutes(30),
            'due_date' => now()->addDays(7),
            'order_id' => (int) $cancelledInvoiceOrder->id,
            'status' => InvoiceStatus::CANCELLED,
        ]);

        Sanctum::actingAs($user);
        $this->forgetExpiredSweepThrottle((int) $user->id);

        $rows = collect($this->getJson('/api/v2/client/orders?page=1&page_size=50')->assertOk()->json('data.list'))->keyBy('id');

        $noInvoiceRow = $rows[$orderWithoutInvoice->id] ?? null;
        $cancelledRow = $rows[$cancelledInvoiceOrder->id] ?? null;
        $this->assertIsArray($noInvoiceRow);
        $this->assertIsArray($cancelledRow);

        $this->assertNull($noInvoiceRow['invoice'] ?? null, '没有关联账单时订单资源必须下发 null，前端据此不给支付入口');
        $this->assertIsArray($cancelledRow['invoice'] ?? null);
        $this->assertSame((int) $cancelledInvoice->id, (int) $cancelledRow['invoice']['id'], '订单资源必须下发账单 id 供前端拼支付页地址');
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $cancelledRow['invoice']['status'], '订单资源必须下发账单状态，账单非待支付时不再给支付入口');
        $this->assertSame(OrderStatus::PENDING, (int) $cancelledRow['status'], '账期内续费影子订单不应被订单列表路径取消，本用例前提成立');

        $noInvoiceDetail = $this->getJson('/api/v2/client/orders/'.$orderWithoutInvoice->id)->assertOk();
        $this->assertNull($noInvoiceDetail->json('data.invoice'), '订单详情同样在无关联账单时下发 null');

        $cancelledDetail = $this->getJson('/api/v2/client/orders/'.$cancelledInvoiceOrder->id)->assertOk();
        $this->assertSame((int) $cancelledInvoice->id, (int) $cancelledDetail->json('data.invoice.id'));
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $cancelledDetail->json('data.invoice.status'));
        $this->assertSame(OrderStatus::PENDING, (int) $cancelledDetail->json('data.status'), '订单详情路径不得因账单已取消而取消账期内续费影子订单');
    }

    /**
     * 定时清理的候选集 SQL 收窄后仍须保持原语义：账期内续费订单/账单、以及无账单的挂账订单都不被取消。
     */
    public function test_schedule_cleanup_keeps_renew_and_admin_manual_records(): void
    {
        [$user, $service, $product] = $this->renewFixture();
        Setting::setValue('automation', 'pending_order_cleanup_enabled', '1');

        $renewOrder = $this->makeOrder($user, $product, $service, ['created_at' => now()->subHours(2)]);
        $renewInvoice = $this->makeInvoice($user, $product, $service, [
            'created_at' => now()->subHours(2),
            'due_date' => now()->addDays(7),
            'order_id' => (int) $renewOrder->id,
        ]);

        $manualOrder = $this->makeOrder($user, $product, $service, [
            'type' => OrderType::NEW,
            'created_at' => now()->subHours(2),
            'config_snapshot' => ['admin_manual' => true],
        ]);

        app(InvoiceCleanupAutomationService::class)->handle();

        $this->assertSame(OrderStatus::PENDING, (int) $renewOrder->fresh()->status, '定时清理不得取消账期内影子续费订单');
        $this->assertSame(InvoiceStatus::UNPAID, (int) $renewInvoice->fresh()->status, '定时清理不得取消账期内续费账单');
        $this->assertSame(OrderStatus::PENDING, (int) $manualOrder->fresh()->status, '定时清理不得取消挂账订单');
    }

    /**
     * @return array{0: User, 1: Service, 2: Product}
     */
    private function renewFixture(): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addDays(10),
        ]);

        return [$user, $service, $product];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInvoice(User $user, Product $product, Service $service, array $overrides = []): Invoice
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $invoice = Invoice::query()->create(array_merge([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'service_id' => $service->id,
            'type' => OrderType::RENEW,
            'amount' => 35.00,
            'status' => InvoiceStatus::UNPAID,
            'billing_cycle' => 'monthly',
            'due_date' => now()->addDays(7),
        ], $overrides));

        $invoice->forceFill(['created_at' => $createdAt])->save();

        return $invoice->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(User $user, Product $product, Service $service, array $overrides = []): Order
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $order = Order::query()->create(array_merge([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'service_id' => $service->id,
            'type' => OrderType::RENEW,
            'amount' => 35.00,
            'quantity' => 1,
            'status' => OrderStatus::PENDING,
            'billing_cycle' => 'monthly',
        ], $overrides));

        $order->forceFill(['created_at' => $createdAt])->save();

        return $order->fresh();
    }

    /**
     * 清理扫描有 60 秒节流（Cache::add checkout:expired-sweep:{userId}）；
     * 用例复用同一缓存时会被跳过一次真实扫描，断言会变"假通过"，这里显式清掉。
     */
    private function forgetExpiredSweepThrottle(int $userId): void
    {
        Cache::forget('checkout:expired-sweep:'.$userId);
    }
}
