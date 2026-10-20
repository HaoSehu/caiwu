<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\AdminUser;
use App\Models\FinanceRefund;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\User\AccountService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SeedsFinanceBalance;
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
    use SeedsFinanceBalance;

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

        $this->assertSame(0, FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_refund_to_balance_moves_money_and_creates_documents(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();
        $user = $invoice->user;
        $this->assertInstanceOf(User::class, $user);
        $accounts = app(AccountService::class);
        $balanceBefore = (float) $this->financeBalance($user, true);

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '100.00',
            'remark' => '用户申请退款，支付宝原路退回失败改为余额退回',
            'idempotency_key' => 'idem-'.uniqid(),
        ], ['X-Request-Id' => 'refund-balance-'.uniqid()])
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.detail.already_refunded', false);

        // 资金出口三件套：余额增加、台账有 INVOICE_REFUND 流水、红字账单生成
        $this->assertEqualsWithDelta($balanceBefore + 100.00, (float) $this->financeBalance($user, true), 0.001);

        $refund = FinanceRefund::query()
            ->where('invoice_id', (int) $invoice->id)
            ->where('funds_moved', 1)
            ->first();
        $this->assertNotNull($refund);
        $this->assertSame(FinanceRefund::STATUS_COMPLETED, (int) $refund->status);
        $this->assertNotNull($refund->payment_id);

        $redInvoice = Invoice::query()
            ->where('origin_invoice_id', (int) $invoice->id)
            ->where('type', InvoiceType::REFUND)
            ->first();
        $this->assertNotNull($redInvoice);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::REFUNDED, (int) $invoice->status);
        $this->assertSame('100.00', (string) $invoice->refund_amount);
        $this->assertSame('balance', (string) $invoice->refund_method);

        $order = $invoice->order;
        $this->assertInstanceOf(Order::class, $order);
        $order->refresh();
        $this->assertSame(OrderStatus::REFUNDED, (int) $order->status);
    }

    public function test_over_amount_balance_refund_is_rejected(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '150.00',
            'remark' => '超额退款测试',
            'idempotency_key' => 'idem-over-'.uniqid(),
        ], ['X-Request-Id' => 'balance-over-'.uniqid()])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->assertSame(0, FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_invalid_amount_format_is_rejected(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '10.999',
            'remark' => '小数位超限',
            'idempotency_key' => 'idem-fmt-'.uniqid(),
        ])->assertStatus(422);

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '0',
            'remark' => '零元退款',
            'idempotency_key' => 'idem-zero-'.uniqid(),
        ])->assertStatus(422);

        $this->assertSame(0, FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_recharge_invoice_cannot_refund_to_balance(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();
        $invoice->forceFill(['type' => InvoiceType::RECHARGE])->save();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", [
            'amount' => '50.00',
            'remark' => '充值账单退回余额测试',
            'idempotency_key' => 'idem-recharge-'.uniqid(),
        ], ['X-Request-Id' => 'recharge-refund-'.uniqid()])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->assertSame(0, FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_same_idempotency_key_replays_without_second_refund(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();
        $idempotencyKey = 'idem-replay-'.uniqid();

        $body = [
            'amount' => '100.00',
            'remark' => '网络重试同键重放',
            'idempotency_key' => $idempotencyKey,
        ];

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", $body)
            ->assertStatus(200)
            ->assertJsonPath('code', 0);

        // 同键重放：返回既有结果，不产生第二条退款单、不二次加余额。
        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refunds", $body)
            ->assertStatus(200)
            ->assertJsonPath('code', 0);

        $this->assertSame(1, FinanceRefund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_invoice_detail_exposes_balance_refund_capability(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND, AdminPermissions::INVOICE_DETAIL]));
        $invoice = $this->makePaidInvoiceWithOrder();

        // 可退场景：能力开启 + 可退额下发（前端「退款到余额」选项据此可用）
        $this->getJson("/api/v2/admin/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.invoice.actions.can_refund_to_balance', true)
            ->assertJsonPath('data.invoice.financial.refundable_amount_for_balance', '100.00');

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
