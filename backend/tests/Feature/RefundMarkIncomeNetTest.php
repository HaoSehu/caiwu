<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Constants\ProductType;
use App\Models\FinanceRefund;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Finance\AdminFinanceQueryService;
use App\Services\Finance\InvoiceRefundService;
use App\Services\System\DashboardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 收入净额口径回归（退款标记批 2026-10-11）：
 * 收入 = Σ max(实收 − Σ(refunds status=1 amount), 0)，红字账单（type=refund）
 * 排除出收入集合。核心断言：部分真退款不双计（X-Y 而非 X-2Y）、
 * 全额真退款贡献 0 而非 -X（修复既有缺陷）、纯标记部分退款按净额计入。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class RefundMarkIncomeNetTest extends TestCase
{
    use DatabaseTransactions;

    private string $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->month = now()->format('Y-m');
    }

    public function test_product_income_counts_partial_marked_refund_by_net_amount(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $this->makePaidSalesInvoice($user, 100.00, ['product_id' => $product->id]);
        $invoice = Invoice::query()->where('user_id', (int) $user->id)->firstOrFail();

        app(InvoiceRefundService::class)->refundInvoiceToBalance(
            User::query()->findOrFail((int) $invoice->user_id),
            $invoice,
            ['amount' => 30.00, 'remark' => '部分退款净额验证'],
            ['operator_id' => 1, 'operator_name' => '测试'],
        );

        $summary = app(AdminFinanceQueryService::class)->productIncomeSummary($this->month);
        $this->assertSame(70.00, round((float) $summary['summary']['total_amount'], 2));
    }

    public function test_fully_marked_invoice_is_excluded_from_income(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $this->makePaidSalesInvoice($user, 100.00, ['product_id' => $product->id]);
        $invoice = Invoice::query()->where('user_id', (int) $user->id)->firstOrFail();

        app(InvoiceRefundService::class)->refundInvoiceToBalance(
            User::query()->findOrFail((int) $invoice->user_id),
            $invoice,
            ['amount' => 100.00, 'remark' => '全额退款净额验证'],
            ['operator_id' => 1, 'operator_name' => '测试'],
        );

        // 全额收口 REFUNDED 后不在 status=PAID 集合，贡献 0。
        $summary = app(AdminFinanceQueryService::class)->productIncomeSummary($this->month);
        $this->assertSame(0.00, round((float) $summary['summary']['total_amount'], 2));
    }

    public function test_monthly_revenue_uses_net_amount(): void
    {
        $user = $this->makeUser();
        $this->makePaidSalesInvoice($user, 100.00);
        $invoice = Invoice::query()->where('user_id', (int) $user->id)->firstOrFail();

        app(InvoiceRefundService::class)->refundInvoiceToBalance(
            User::query()->findOrFail((int) $invoice->user_id),
            $invoice,
            ['amount' => 30.00, 'remark' => '月度收入净额验证'],
            ['operator_id' => 1, 'operator_name' => '测试'],
        );

        $revenue = app(DashboardService::class)->monthlyRevenue();
        $totalCents = (int) collect($revenue['revenue_by_product'])->sum('amount') * 100;

        $this->assertSame(7000, (int) round($totalCents));
    }

    public function test_partial_real_refund_with_red_invoice_is_not_double_deducted(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $this->makePaidSalesInvoice($user, 100.00, ['product_id' => $product->id]);
        $invoice = Invoice::query()->where('user_id', (int) $user->id)->firstOrFail();

        // 手工模拟「部分真退款」终态：refunds 行 + 红字账单（createBalanceRefundDocuments
        // 的落库形态；真退款服务由 RefundInvoiceToBalanceTest 固化，此处只造数据）。
        $refund = FinanceRefund::query()->create([
            'refund_no' => 'RFD'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'amount' => '30.00',
            'status' => FinanceRefund::STATUS_COMPLETED,
            'funds_moved' => 1,
            'data_origin' => FinanceRefund::ORIGIN_NATIVE,
            'currency' => 'CNY',
            'reason' => '部分真退款',
            'refunded_at' => now(),
        ]);
        Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'origin_invoice_id' => (int) $invoice->id,
            'type' => InvoiceType::REFUND,
            'amount' => '-30.00',
            'paid_amount' => '-30.00',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);
        $refund->forceFill(['refunded_at' => now()])->save();

        // 旧口径：原单 100 + 红字 -30 = 70（恰好相等但机制不同）；
        // 「只做净额不排红字」的错误口径会得到 100-30-30 = 40。新口径必须 = 70。
        $summary = app(AdminFinanceQueryService::class)->productIncomeSummary($this->month);
        $this->assertSame(70.00, round((float) $summary['summary']['total_amount'], 2));

        // Dashboard 同口径：红字被排除 + 净额扣减，仍是 70 而非 40。
        $revenue = app(DashboardService::class)->monthlyRevenue();
        $totalCents = (int) collect($revenue['revenue_by_product'])->sum('amount') * 100;
        $this->assertSame(7000, (int) round($totalCents));
    }

    public function test_full_real_refund_contributes_zero_instead_of_negative(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $this->makePaidSalesInvoice($user, 100.00, ['product_id' => $product->id]);
        $invoice = Invoice::query()->where('user_id', (int) $user->id)->firstOrFail();

        // 全额真退款终态：原单 REFUNDED + 红字账单 PAID(-100) + refunds 行。
        $invoice->forceFill(['status' => InvoiceStatus::REFUNDED, 'refund_amount' => '100.00', 'refunded_at' => now()])->save();
        FinanceRefund::query()->create([
            'refund_no' => 'RFD'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'amount' => '100.00',
            'status' => FinanceRefund::STATUS_COMPLETED,
            'funds_moved' => 1,
            'data_origin' => FinanceRefund::ORIGIN_NATIVE,
            'currency' => 'CNY',
            'reason' => '全额真退款',
            'refunded_at' => now(),
        ]);
        Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'origin_invoice_id' => (int) $invoice->id,
            'type' => InvoiceType::REFUND,
            'amount' => '-100.00',
            'paid_amount' => '-100.00',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        // 既有缺陷：旧口径下原单被 status 过滤、红字又贡献 -100 → 收入 -100。
        // 新口径红字排除 + 净额扣减 → 0。
        $summary = app(AdminFinanceQueryService::class)->productIncomeSummary($this->month);
        $this->assertSame(0.00, round((float) $summary['summary']['total_amount'], 2));

        $revenue = app(DashboardService::class)->monthlyRevenue();
        $totalCents = (int) collect($revenue['revenue_by_product'])->sum('amount') * 100;
        $this->assertSame(0, (int) round($totalCents));
    }

    /**
     * 造销售类已付账单（type=new + 网关支付单），paid_at 落在本月。
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makePaidSalesInvoice(User $user, float $amount, array $overrides = []): Invoice
    {
        $invoice = Invoice::query()->create(array_merge([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => $amount,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ], $overrides));
        Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => $amount,
            'status' => PaymentStatus::SUCCESS,
            'trade_no' => 'TRADE'.date('YmdHis').mt_rand(100000, 999999),
            'paid_at' => now(),
            'callback_raw' => ['source' => 'alipay_precreate'],
        ]);

        return $invoice;
    }

    private function makeProduct(): Product
    {
        return Product::query()->create([
            'product_type' => ProductType::CLOUD_SERVER,
            'service_type_code' => ProductType::CLOUD_SERVER,
            'status' => 1,
            'stock' => -1,
            'pricing' => ['monthly' => '10.00'],
        ]);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'income-net-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'income-net-tester',
            'total_sales_amount' => 0,
        ]);
    }
}
