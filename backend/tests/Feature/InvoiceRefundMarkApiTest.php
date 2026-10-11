<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\AccountTransaction;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RechargeRecord;
use App\Models\Refund;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 退款标记接口层回归（/v2/admin/invoices|orders/{id}/refund-marks）：
 * 权限 403、参数校验 422、订单侧入口联动、无账单订单拒绝、HTTP 层资金红线。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class InvoiceRefundMarkApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_requires_finance_refund_permission(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::INVOICE_MANAGE]));
        $invoice = $this->makePaidInvoiceWithOrder();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refund-marks", [
            'amount' => '30.00',
            'remark' => '无权限测试',
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);

        $this->assertSame(0, Refund::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_mark_invoice_refund_via_api_with_permission(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();

        $ledgerBefore = AccountTransaction::query()->where('user_id', (int) $invoice->user_id)->count();
        $rechargeBefore = RechargeRecord::query()->where('user_id', (int) $invoice->user_id)->count();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refund-marks", [
            'amount' => '30.00',
            'remark' => '线下银行转账退款，流水号 6222****8899',
        ], ['X-Request-Id' => 'mark-api-'.uniqid()])
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.detail.already_marked', false)
            ->assertJsonPath('data.detail.invoice.refund_amount', '30.00');

        // HTTP 层再验资金红线：余额/台账/充值记录/红字账单零变化。
        $this->assertSame($ledgerBefore, AccountTransaction::query()->where('user_id', (int) $invoice->user_id)->count());
        $this->assertSame($rechargeBefore, RechargeRecord::query()->where('user_id', (int) $invoice->user_id)->count());
        $this->assertSame(0, Invoice::query()->where('origin_invoice_id', (int) $invoice->id)->count());
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
    }

    public function test_invalid_amount_format_is_rejected(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refund-marks", [
            'amount' => '30.005',
            'remark' => '金额格式错误测试',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refund-marks", [
            'remark' => '缺金额测试',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);
    }

    public function test_missing_remark_is_rejected(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();

        $this->postJson("/api/v2/admin/invoices/{$invoice->id}/refund-marks", [
            'amount' => '30.00',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);
    }

    public function test_mark_order_refund_via_api_resolves_invoice(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $invoice = $this->makePaidInvoiceWithOrder();
        $order = $invoice->order;
        $this->assertInstanceOf(Order::class, $order);

        $this->postJson("/api/v2/admin/orders/{$order->id}/refund-marks", [
            'amount' => '20.00',
            'remark' => '订单侧退款标记',
        ])
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.detail.invoice.id', (int) $invoice->id);

        $invoice->refresh();
        $this->assertSame('20.00', (string) $invoice->refund_amount);
    }

    public function test_order_without_invoice_is_rejected_via_api(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::FINANCE_REFUND]));
        $user = User::query()->create([
            'email' => 'mark-api-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'mark-api-tester',
            'total_sales_amount' => 0,
        ]);
        $order = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'status' => OrderStatus::PENDING,
        ]);

        $this->postJson("/api/v2/admin/orders/{$order->id}/refund-marks", [
            'amount' => '50.00',
            'remark' => '无账单订单测试',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '该订单未关联账单，无法标记退款');
    }

    /**
     * 造「账单 PAID + 关联订单 PAID + 主支付单可退基数 100」场景。
     */
    private function makePaidInvoiceWithOrder(): Invoice
    {
        $user = User::query()->create([
            'email' => 'mark-api-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'mark-api-tester',
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
            'label' => '退款标记接口测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '退款标记接口测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
