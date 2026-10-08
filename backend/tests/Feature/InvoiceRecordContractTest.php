<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderType;
use App\Constants\ServiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Admin\V2\AdminManualEntryV2Service;
use App\Services\Finance\InvoiceService;
use App\Services\Provisioning\ServiceRenewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 账单记录契约基线（方案 2 阶段一）：对不依赖上游报价的创建路径真实写库，
 * 断言记录契约不变式 I1-I7 的适用子集。任何新建账单路径都应在此补用例。
 *
 * 不变式（定义见 docs/执行计划/进行中/订单与账单记录内容统一方案-2026-10-06.md）：
 * I1 目录价还原；I2 折扣分列；I3 券四件套；I4 快照组；I5 绑定（购买类有订单、
 * 充值/返利/扣款/退款无订单属设计）；I6 type 值域封闭；I7 trace 贯穿。
 *
 * 结账路径（新购含券/会员折扣）由 CouponDiscountDisplayEndToEndTest 全量覆盖；
 * 升级/流量包路径依赖上游报价夹具，随其专属特征测试补充。
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class InvoiceRecordContractTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------- 非购买类（无订单属设计，I5）

    public function test_recharge_invoice_satisfies_contract(): void
    {
        $user = User::factory()->create();
        $invoice = app(InvoiceService::class)->createForRecharge($user, 88.0, null, '契约充值', 'trace-recharge');

        $this->assertSame('recharge', (string) $invoice->type, 'I6 type 值域');
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('88.00', number_format((float) $invoice->amount, 2, '.', ''));
        $this->assertSame('88.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(0.0, (float) $invoice->discount, 'I2 非购买类折扣恒 0');
        $this->assertSame(0.0, (float) $invoice->member_discount_amount, 'I2 非购买类无会员折扣');
        $this->assertNull($invoice->order_id, 'I5 充值账单无订单属设计');
        $this->assertSame('trace-recharge', (string) $invoice->trace_id, 'I7 trace 贯穿');
    }

    public function test_referral_credit_invoice_satisfies_contract(): void
    {
        $user = User::factory()->create();
        $invoice = app(InvoiceService::class)->createForReferralCredit($user, 12.5, '契约奖励');

        $this->assertSame('referral_credit', (string) $invoice->type, 'I6 type 值域');
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('12.50', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertNull($invoice->order_id, 'I5 推荐奖励账单无订单属设计');
    }

    public function test_deduction_invoice_satisfies_contract(): void
    {
        $user = User::factory()->create();
        $invoice = app(InvoiceService::class)->createForDeduction($user, 3.3, '契约扣款');

        $this->assertSame('deduction', (string) $invoice->type, 'I6 type 值域');
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('3.30', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertNull($invoice->order_id, 'I5 扣款账单无订单属设计');
    }

    // ------------------------------------------------- 手工账单（createDirect 透传，W5 回归）

    public function test_direct_manual_invoice_passes_through_member_discount_and_paid_amount(): void
    {
        $user = User::factory()->create();
        $invoice = app(InvoiceService::class)->createDirect([
            'user_id' => (int) $user->id,
            'type' => 'manual',
            'amount' => '50.00',
            'discount' => '5.00',
            'member_discount_amount' => '7.00',
            'member_discount_snapshot' => ['member_level_name' => '契约等级'],
            'paid_amount' => '38.00',
            'trace_id' => 'trace-direct',
        ]);

        $this->assertSame('manual', (string) $invoice->type, 'I6 type 值域');
        $this->assertSame('5.00', number_format((float) $invoice->discount, 2, '.', ''), 'I2 券减免透传');
        $this->assertSame('7.00', number_format((float) $invoice->member_discount_amount, 2, '.', ''), 'W5 会员折扣透传');
        $this->assertSame(['member_level_name' => '契约等级'], $invoice->member_discount_snapshot, 'W5 会员快照透传');
        $this->assertSame('38.00', number_format((float) $invoice->paid_amount, 2, '.', ''), 'W5 已付金额透传');
        $this->assertSame('trace-direct', (string) $invoice->trace_id, 'I7 trace 贯穿');
    }

    // ------------------------------------------------- 补录订单路径（W3 快照 + W2 type 回归）

    public function test_manual_order_and_invoice_satisfy_contract(): void
    {
        [$user, $service] = $this->serviceFixture();

        $result = app(AdminManualEntryV2Service::class)->createManualOrder($user, [
            'service_id' => (int) $service->id,
            'type' => OrderType::NEW,
            'amount' => '35.00',
            'billing_cycle' => 'monthly',
            'payment_gateway' => 'bank_transfer',
            'remark' => '契约补录订单',
        ], ['operator_name' => '契约管理员', 'trace_id' => 'trace-manual-order']);

        $order = Order::query()->findOrFail((int) $result['id']);
        $invoice = Invoice::query()->findOrFail((int) $result['detail']['invoice']['id']);

        // W3 回归：补录订单必须落商品快照（否则 createFromOrder 继承 null）
        $this->assertSame((string) $service->product->name, (string) $order->product_spec_snapshot, 'W3 补录订单缺商品快照');
        $this->assertNotSame('', (string) $order->product_type_snapshot, 'W3 补录订单缺商品类型快照');
        // W2 回归：新购补录账单 type 必须是 new 而非 normal
        $this->assertSame('new', (string) $invoice->type, 'W2 新购补录账单 type 应归一为 new');
        // I5/I7：账单绑定订单、trace 同源
        $this->assertSame((int) $order->id, (int) $invoice->order_id, 'I5 购买类账单必须绑定订单');
        $this->assertSame((string) $order->trace_id, (string) $invoice->trace_id, 'I7 trace 同源');
        // I1：补录无折扣，目录价=应付价
        $this->assertSame('35.00', number_format((float) $invoice->amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $invoice->discount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $invoice->member_discount_amount, 2, '.', ''));
        // I4：账单继承商品快照
        $this->assertSame((string) $order->product_spec_snapshot, (string) $invoice->product_spec_snapshot, 'I4 账单应继承商品快照');
    }

    // ------------------------------------------------- 续费账单路径（I4 续费元数据）

    public function test_renew_invoice_path_satisfies_contract(): void
    {
        [$user, $service] = $this->serviceFixture();

        $invoice = app(ServiceRenewService::class)->createRenewInvoiceForUser(
            $user,
            (int) $service->id,
            'monthly'
        );

        $this->assertSame('renew', (string) $invoice->type, 'I6 type 值域');
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->status);
        $this->assertNotNull($invoice->order_id, 'I5 续费账单绑定影子订单');
        $this->assertSame((int) $service->id, (int) $invoice->service_id, 'I5 续费账单关联服务');
        $this->assertSame('0.00', number_format((float) $invoice->discount, 2, '.', ''));
        // I4：续费 config_snapshot 承载续费元数据（type 私有载荷），不应为空
        $this->assertIsArray($invoice->config_snapshot);
        $this->assertNotSame([], $invoice->config_snapshot, 'I4 续费账单缺 config_snapshot 续费元数据');
        // 与影子订单金额同源
        $order = Order::query()->findOrFail((int) $invoice->order_id);
        $this->assertSame(number_format((float) $invoice->amount, 2, '.', ''), number_format((float) $order->amount, 2, '.', ''), 'I1 续费订单与账单金额同源');
    }

    // ------------------------------------------------------------------ 夹具

    /**
     * @return array{0: User, 1: Service}
     */
    private function serviceFixture(): array
    {
        $user = User::factory()->create(['is_verified' => 1]);
        $product = Product::query()->create([
            'name' => '契约商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '20.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);
        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 20.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        return [$user, $service];
    }
}
