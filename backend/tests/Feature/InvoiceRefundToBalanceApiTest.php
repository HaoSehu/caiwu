<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\User;
use App\Services\User\AccountService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 退款到余额接口层回归（/v2/admin/invoices/{id}/refunds）：
 * 2026-10-11 恢复 2026-10-03 下线的管理员退款资金出口，权限复用 finance.refund，
 * 与纯标记通道**互斥**（一单只选一种，2026-10-11 裁决）。
 *
 * 覆盖：权限 403、参数校验 422、成功退款（余额增加 + 台账 + 红字账单 + 充值记录）、
 * 通道互斥双向拒绝、超额拒绝、充值账单拒绝。使用 DatabaseTransactions，结束回滚。
 */
class InvoiceRefundToBalanceApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_requires_finance_refund_permission(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::INVOICE_MANAGE]));
        $invoice = $this->makePaidInvoiceWithOrder();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '50.00',
            'remark' => '无权限测试',
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);

        $this->assertSame(0, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_refund_to_balance_moves_money_and_creates_documents(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();
        $user = $invoice->user;
        $this->assertInstanceOf(User::class, $user);
        $accounts = app(AccountService::class);
        $balanceBefore = (float) $accounts->cashBalance($user, true);

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '100.00',
            'remark' => '用户申请退款，支付宝原路退回失败改为余额退回',
        ], ['X-Request-Id' => 'refund-balance-'.uniqid()])
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.detail.already_refunded', false);

        // 资金出口三件套：余额增加、台账有 INVOICE_REFUND 流水、红字账单生成
        $this->assertEqualsWithDelta($balanceBefore + 100.00, (float) $accounts->cashBalance($user, true), 0.001);

        $refund = Refund::query()
            ->where('invoice_id', (int) $invoice->id)
            ->where('refund_method', Refund::METHOD_BALANCE)
            ->first();
        $this->assertNotNull($refund);
        $this->assertSame(Refund::STATUS_COMPLETED, (int) $refund->status);
        $this->assertNotNull($refund->payment_id);

        $redInvoice = Invoice::query()
            ->where('origin_invoice_id', (int) $invoice->id)
            ->where('type', InvoiceType::REFUND)
            ->first();
        $this->assertNotNull($redInvoice);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->status);
        $this->assertSame('100.00', (string) $invoice->refund_amount);
        $this->assertSame(Refund::METHOD_BALANCE, (string) $invoice->refund_method);

        $order = $invoice->order;
        $this->assertInstanceOf(Order::class, $order);
        $order->refresh();
        $this->assertSame(OrderStatus::REFUNDED, (int) $order->status);
    }

    public function test_balance_refund_is_rejected_after_manual_mark(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();

        // 先走纯标记通道
        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refund-marks", [
            'amount' => '30.00',
            'remark' => '线下已退 30 元',
        ], ['X-Request-Id' => 'mark-first-'.uniqid()])->assertStatus(200);

        // 再走真退款通道必须被拒（一单只选一种）
        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '70.00',
            'remark' => '剩余部分退回余额',
        ], ['X-Request-Id' => 'balance-after-mark-'.uniqid()])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->assertSame(1, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
    }

    public function test_manual_mark_is_rejected_after_balance_refund(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '100.00',
            'remark' => '整笔退回余额',
        ], ['X-Request-Id' => 'balance-first-'.uniqid()])->assertStatus(200);

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refund-marks", [
            'amount' => '10.00',
            'remark' => '事后重复标记',
        ], ['X-Request-Id' => 'mark-after-balance-'.uniqid()])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->assertSame(1, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_over_amount_balance_refund_is_rejected(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '150.00',
            'remark' => '超额退款测试',
        ], ['X-Request-Id' => 'balance-over-'.uniqid()])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->assertSame(0, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_invalid_amount_format_is_rejected(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '10.999',
            'remark' => '小数位超限',
        ])->assertStatus(422);

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '0',
            'remark' => '零元退款',
        ])->assertStatus(422);

        $this->assertSame(0, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_recharge_invoice_cannot_refund_to_balance(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();
        $invoice->forceFill(['type' => InvoiceType::RECHARGE])->save();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '50.00',
            'remark' => '充值账单退回余额测试',
        ], ['X-Request-Id' => 'recharge-refund-'.uniqid()])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->assertSame(0, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_invoice_detail_exposes_balance_refund_capability(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND, AdminPermissions::INVOICE_DETAIL]));
        $invoice = $this->makePaidInvoiceWithOrder();

        // 可退场景：能力开启 + 可退额下发（前端「退款到余额」选项据此可用）
        $this->getJson("/api/v2/admin/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.invoice.actions.can_refund_to_balance', true)
            ->assertJsonPath('data.invoice.actions.can_mark_refund', true)
            ->assertJsonPath('data.invoice.financial.has_manual_mark_refund', false)
            ->assertJsonPath('data.invoice.financial.refundable_amount_for_balance', '100.00');

        // 走过纯标记后互斥：余额退款能力关闭，标记痕迹对前端可见
        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refund-marks", [
            'amount' => '40.00',
            'remark' => '线下已退 40 元',
        ], ['X-Request-Id' => 'mark-then-detail-'.uniqid()])->assertStatus(200);

        $this->getJson("/api/v2/admin/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.invoice.actions.can_refund_to_balance', false)
            ->assertJsonPath('data.invoice.financial.has_manual_mark_refund', true);
    }

    private function makePaidInvoiceWithOrder(): Invoice
    {
        $user = User::query()->create([
            'email' => 'refund-balance-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'refund-balance-tester',
            'total_sales_amount' => 0,
        ]);
        $order = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'status' => OrderStatus::PENDING,
        ]);
        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
            'order_id' => $order->id,
        ]);
        Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 100.00,
            'status' => PaymentStatus::SUCCESS,
            'trade_no' => 'TRADE'.date('YmdHis').mt_rand(100000, 999999),
            'paid_at' => now(),
            'callback_raw' => ['source' => 'alipay_precreate'],
        ]);
        $order->forceFill(['status' => OrderStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        return $invoice->refresh();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '退款到余额接口测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '退款到余额接口测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
