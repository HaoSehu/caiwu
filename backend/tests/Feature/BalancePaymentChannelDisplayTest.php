<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\ProductType;
use App\Exceptions\BusinessException;
use App\Models\FinanceLedgerEntry;
use App\Models\FirstProductGroup;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use App\Models\User;
use App\Services\Finance\BalanceInvoicePaymentService;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\ClientInvoicePaymentWorkflowService;
use App\Services\Finance\InvoiceService;
use App\Services\Order\ClientOrderQueryService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\ProductCatalog\ProductAdminService;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 余额支付的渠道展示回归。
 *
 * 背景：余额支付按 ARCHITECTURE.md 的职责边界不产生 Payment 记录，
 * 它唯一的资金事实是账单名下 event_type=invoice_payment 的现金扣款台账。
 * 账单/订单的「支付渠道」若只从 payments 派生，余额支付的订单在
 * 用户端订单列表、管理端订单支付链、账单支付摘要里都只会显示占位符。
 *
 * 本测试锁定四件事：
 * 1. 余额支付后，账单摘要与订单渠道都能还原出「余额支付」；
 * 2. 第三方支付的账单不会被台账回落改写成「余额支付」；
 * 3. 0 元账单同样只给余额支付一个选项，支付时不扣款且留下 0 元结清证据；
 * 4. 历史导入的 invoice_payment 台账（operator_type 为 NULL）不算余额支付证据。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class BalancePaymentChannelDisplayTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    public function test_balance_paid_order_shows_balance_channel_on_all_surfaces(): void
    {
        $this->mock(PaidOrderBusinessFlowDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchPaidInvoice')->andReturnNull();
        });

        [$user, $order, $invoice] = $this->makeTrade('800.00');
        $this->seedFinanceBalance($user, 800.00);

        app(BalanceInvoicePaymentService::class)->payByBalance($invoice, $user);

        // 余额支付不产生支付单，这是本测试成立的前提
        $this->assertSame(0, Payment::query()->where('invoice_id', (int) $invoice->id)->count());

        $invoiceDetail = app(InvoiceService::class)->clientDetail($invoice->fresh());
        $this->assertNotNull($invoiceDetail['payment_summary'], '余额支付的账单必须给出支付方式');
        $this->assertSame(PaymentGatewayCode::BALANCE, $invoiceDetail['payment_summary']['gateway']);
        $this->assertSame('余额支付', $invoiceDetail['payment_summary']['channel_name']);
        // 余额支付没有支付单号，不得凭空编造
        $this->assertSame('', $invoiceDetail['payment_summary']['payment_no']);

        $orderRow = app(ClientOrderQueryService::class)->detail((int) $user->id, (int) $order->id, []);
        $this->assertSame('余额支付', $orderRow['channel_name'], '用户端订单列表的支付渠道为空');
        $this->assertSame('余额支付', $orderRow['gateway_label']);
    }

    public function test_third_party_payment_is_not_overridden_by_balance_fallback(): void
    {
        [$user, $order, $invoice] = $this->makeTrade('10.00');

        $payment = new Payment;
        $payment->payment_no = 'PAY-'.uniqid();
        $payment->user_id = $user->id;
        $payment->invoice_id = $invoice->id;
        $payment->gateway_key = PaymentGatewayCode::ALIPAY;
        $payment->amount = '10.00';
        $payment->status = 1;
        $payment->paid_at = now();
        // 创建守卫在 creating 阶段读该开关，必须在 save() 之前置位。
        $payment->allowNonThirdPartyGateway = true;
        $payment->save();

        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => '10.00', 'paid_at' => now()])->save();

        $summary = app(InvoiceService::class)->clientDetail($invoice->fresh())['payment_summary'];
        $this->assertNotNull($summary);
        $this->assertSame(PaymentGatewayCode::ALIPAY, $summary['gateway'], '第三方支付不得被台账回落改写');
        $this->assertSame('支付宝', $summary['channel_name']);

        $orderRow = app(ClientOrderQueryService::class)->detail((int) $user->id, (int) $order->id, []);
        $this->assertSame('支付宝', $orderRow['channel_name']);
    }

    public function test_unpaid_invoice_has_no_channel_fallback(): void
    {
        [$user, $order, $invoice] = $this->makeTrade('10.00');

        $detail = app(InvoiceService::class)->clientDetail($invoice->fresh());
        $this->assertNull($detail['payment_summary'], '待支付账单不得给出支付方式');

        $orderRow = app(ClientOrderQueryService::class)->detail((int) $user->id, (int) $order->id, []);
        $this->assertSame('', $orderRow['channel_name'], '待支付订单的渠道应为空串，由前端兜底占位符');
    }

    public function test_zero_amount_invoice_only_offers_balance_and_settles_without_charge(): void
    {
        $this->mock(PaidOrderBusinessFlowDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchPaidInvoice')->andReturnNull();
        });

        [$user, $order, $invoice] = $this->makeTrade('0.00');
        $this->seedFinanceBalance($user, 0.00);

        $methods = app(ClientInvoicePaymentWorkflowService::class)->availablePaymentMethods(0.0);
        $this->assertSame(['balance'], array_map(static fn (array $m): string => (string) $m['key'], $methods), '0 元账单只允许余额支付');

        $balanceBefore = $this->financeBalance($user);

        // 余额为 0 也必须能结清 0 元账单
        app(BalanceInvoicePaymentService::class)->payByBalance($invoice, $user);

        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->fresh()->status);
        $this->assertSame(OrderStatus::PAID, (int) $order->fresh()->status);
        $this->assertSame($balanceBefore, $this->financeBalance($user), '0 元账单不得扣减余额');

        // 留下 0 元结清证据，账单渠道才能显示「余额支付」
        $evidence = FinanceLedgerEntry::query()
            ->where('source_type', 'invoice')
            ->where('source_id', (int) $invoice->id)
            ->where('change_amount', 0)
            ->first();
        $this->assertNotNull($evidence, '0 元账单缺少余额结清证据');
        $this->assertSame('invoice_payment', (string) $evidence->event_type);

        $summary = app(InvoiceService::class)->clientDetail($invoice->fresh())['payment_summary'];
        $this->assertNotNull($summary);
        $this->assertSame('余额支付', $summary['channel_name']);
    }

    public function test_admin_user_invoice_list_shows_balance_channel(): void
    {
        $this->mock(PaidOrderBusinessFlowDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchPaidInvoice')->andReturnNull();
        });

        [$user, , $invoice] = $this->makeTrade('50.00');
        $this->seedFinanceBalance($user, 50.00);

        app(BalanceInvoicePaymentService::class)->payByBalance($invoice, $user);

        // 管理端用户详情账单列表：payment_summary 由 UserService 单独组装
        $row = app(UserService::class)->invoices($user, [])->items()[0];
        $this->assertSame('余额支付', $row['payment_summary']['channel_name'] ?? null);
    }

    public function test_legacy_imported_invoice_payment_ledger_is_not_treated_as_balance(): void
    {
        [$user, $order, $invoice] = $this->makeTrade('30.00');

        // 历史导入（RebuildFinanceLedgerCommand）写下的 invoice_payment 行 operator_type 为 NULL：
        // 既无支付单、也无操作者，无法证明这笔扣款走的什么渠道，必须留空而不是臆造为「余额支付」。
        FinanceLedgerEntry::query()->create([
            'entry_no' => 'LG-'.uniqid(),
            'user_id' => (int) $user->id,
            'event_type' => FinanceLedgerEventType::INVOICE_PAYMENT,
            'change_amount' => '-30.00',
            'balance_after' => '0.00',
            'currency' => 'CNY',
            'source_type' => 'invoice',
            'source_id' => (int) $invoice->id,
            'operator_type' => null,
            'remark' => '历史导入扣款',
            'created_at' => now(),
        ]);

        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => '30.00', 'paid_at' => now()])->save();

        // 账单摘要不得回落出「余额支付」
        $summary = app(InvoiceService::class)->clientDetail($invoice->fresh())['payment_summary'];
        $this->assertNull($summary, '历史导入台账不得被判定为余额支付');

        // 订单渠道同样必须留空，由前端兜底占位符
        $orderRow = app(ClientOrderQueryService::class)->detail((int) $user->id, (int) $order->id, []);
        $this->assertSame('', $orderRow['channel_name'], '历史导入台账不得让订单显示余额支付');
    }

    /**
     * operator_type 是后加的列，历史行普遍为 NULL/'legacy'，但只要 trace_id 或备注能证明
     * 「这是某张账单的扣款」，它仍然是真实的余额支付（自动续费就是这么写的）。
     * 用 operator_type 做白名单会把这些真实扣款误杀。
     */
    public function test_balance_ledger_without_operator_type_but_with_trace_is_recognized(): void
    {
        [$user, $order, $invoice] = $this->makeTrade('25.00');

        FinanceLedgerEntry::query()->create([
            'entry_no' => 'LG-'.uniqid(),
            'user_id' => (int) $user->id,
            'event_type' => FinanceLedgerEventType::INVOICE_PAYMENT,
            'change_amount' => '-25.00',
            'balance_after' => '0.00',
            'currency' => 'CNY',
            'source_type' => 'invoice',
            'source_id' => (int) $invoice->id,
            'operator_type' => 'legacy',
            'operator_name' => 'auto_renew',
            'trace_id' => 'auto_renew:service:147:202606240155',
            'remark' => '支付账单 '.$invoice->invoice_no,
            'created_at' => now(),
        ]);

        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => '25.00', 'paid_at' => now()])->save();

        $summary = app(InvoiceService::class)->clientDetail($invoice->fresh())['payment_summary'];
        $this->assertNotNull($summary, '带 trace_id 的自动续费扣款必须能还原渠道');
        $this->assertSame('余额支付', $summary['channel_name']);

        $orderRow = app(ClientOrderQueryService::class)->detail((int) $user->id, (int) $order->id, []);
        $this->assertSame('余额支付', $orderRow['channel_name']);
    }

    public function test_zero_price_product_creates_order_and_invoice_normally(): void
    {
        $this->mock(PaidOrderBusinessFlowDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchPaidInvoice')->andReturnNull();
        });

        // 0 元商品必须能正常下单：账单与订单照常创建，收口走余额支付。
        $first = FirstProductGroup::query()->firstOrCreate(
            ['code' => ProductType::VPS],
            [
                'product_type' => ProductType::CLOUD_SERVER,
                'name' => '云服务器',
                'slug' => 'zero-price-test-first-'.uniqid(),
                'sort_order' => 999,
                'is_visible' => 1,
                'is_system' => 0,
            ]
        );
        if ((int) $first->is_visible !== 1) {
            $first->forceFill(['is_visible' => 1])->save();
        }

        $second = SecondProductGroup::query()->create([
            'first_product_group_id' => $first->id,
            'name' => '零元测试二级',
            'slug' => 'zero-price-test-second-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 1,
        ]);
        $third = ThirdProductGroup::query()->create([
            'second_product_group_id' => $second->id,
            'name' => '零元测试三级',
            'slug' => 'zero-price-test-third-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 1,
        ]);

        $product = app(ProductAdminService::class)->createProduct([
            'third_product_group_id' => (int) $third->id,
            'custom_display_name' => '零元测试商品',
            'pricing' => ['monthly' => '0.00'],
            'stock' => 100,
            'status' => 1,
        ]);

        // 管理端归一化必须原样保留显式 0 元：一旦被丢成「未配置」，
        // 下单会被 hasBillingCyclePrice() 判为配置错误，0 元商品永远下不了单。
        $this->assertSame('0.00', $product->pricing['monthly'] ?? null, '管理端创建后月价必须保留为 0.00');
        $this->assertTrue($product->hasBillingCyclePrice('monthly'), '显式 0 元必须被识别为已配置周期');

        $user = User::query()->create([
            'email' => 'zero-price-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'zero-price-tester',
            'total_sales_amount' => 0,
            'status' => 1,
            'is_verified' => 1,
        ]);

        $checkout = app(CheckoutService::class);
        $quote = $checkout->quote($product, 'monthly', [], 1);
        $issued = app(CheckoutSecurityService::class)->issueQuoteToken(
            (int) $product->id,
            'monthly',
            [],
            $quote,
            ['user_id' => (int) $user->id],
        );

        $invoice = $checkout->create((int) $user->id, [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'config' => [],
            'quote_token' => (string) $issued['quote_token'],
        ], ['idempotency_key' => 'zero-price-'.uniqid('', true)]);

        $this->assertSame('0.00', number_format((float) $invoice->amount, 2, '.', ''), '0 元商品必须生成 0 元账单');
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->status);

        $order = Order::query()->where('id', (int) $invoice->order_id)->first();
        $this->assertNotNull($order, '0 元账单必须同时投影出订单');
        $this->assertSame(OrderStatus::PENDING, (int) $order->status);

        // 未配置的周期仍然必须拒绝，不能因为放开 0 元而放过配置错误。
        // 同样走管理端造数，避免测试专用写入路径掩盖归一化问题。
        $unconfigured = app(ProductAdminService::class)->createProduct([
            'third_product_group_id' => (int) $third->id,
            'custom_display_name' => '未配置周期商品',
            'pricing' => ['annually' => '100.00'],
            'stock' => 100,
            'status' => 1,
        ]);

        $this->expectException(BusinessException::class);
        $checkout->quote($unconfigured, 'monthly', [], 1);
    }

    /**
     * @return array{0: User, 1: Order, 2: Invoice}
     */
    private function makeTrade(string $amount): array
    {
        $user = User::query()->create([
            'email' => 'balance-channel-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'balance-channel-tester',
            'total_sales_amount' => 0,
            'status' => 1,
        ]);

        $order = Order::query()->create([
            'order_no' => 'OT'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'status' => OrderStatus::PENDING,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ]);

        return [$user, $order, $invoice];
    }
}
