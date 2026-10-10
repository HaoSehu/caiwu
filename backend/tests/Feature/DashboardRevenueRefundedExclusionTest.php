<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Models\Invoice;
use App\Models\User;
use App\Services\System\DashboardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 仪表盘月度收入口径（B5：V7 覆盖缺口「收入统计排除已退款」）：
 * 1. 本月收入按 status=PAID 统计——已退款/待付/已取消账单一律不计入；
 * 2. 产品维度与每日趋势同口径，避免两处口径漂移；
 * 3. 充值类账单不属销售收入（docblock 契约）。
 * 存量库可能有当月数据，全部断言使用基线增量。使用 DatabaseTransactions。
 */
class DashboardRevenueRefundedExclusionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_monthly_revenue_counts_only_paid_sales_invoices(): void
    {
        Cache::flush();
        $baseline = app(DashboardService::class)->monthlyRevenue();
        $baselineTotal = (float) collect($baseline['revenue_by_product'])->sum('amount');
        $baselineToday = collect($baseline['daily_revenue'])->firstWhere('date', now()->format('Y-m-d'));

        $user = $this->makeUser();
        // 应计入：本月已付销售账单（+120）。
        $this->makeInvoice($user, InvoiceStatus::PAID, '120.00', now(), '收入口径产品A');
        // 不应计入：已退款（+80）、待付（+30）、已取消（+10）、充值类（+50）。
        $this->makeInvoice($user, InvoiceStatus::REFUNDED, '80.00', now(), '收入口径产品A');
        $this->makeInvoice($user, InvoiceStatus::UNPAID, '30.00');
        $this->makeInvoice($user, InvoiceStatus::CANCELLED, '10.00', now(), '收入口径产品B');
        $this->makeInvoice($user, InvoiceStatus::PAID, '50.00', now(), '收入口径充值', InvoiceType::RECHARGE);

        Cache::flush();
        $after = app(DashboardService::class)->monthlyRevenue();

        // 产品维度合计（top-8 截断会把长尾并入「其他」，合计不受截断影响）：
        // 任何错误计入都会让增量偏离 120（如含已退款为 200、含充值为 170、全含为 290）。
        $afterTotal = (float) collect($after['revenue_by_product'])->sum('amount');
        $this->assertEqualsWithDelta(
            $baselineTotal + 120.0,
            $afterTotal,
            0.001,
            '月度收入仅统计已付销售账单：已退款/待付/已取消/充值均不计入'
        );

        // 每日趋势与产品维度同口径：同日已退款不增加当日收入与笔数。
        $afterToday = collect($after['daily_revenue'])->firstWhere('date', now()->format('Y-m-d'));
        $this->assertEqualsWithDelta(
            (float) ($baselineToday['amount'] ?? 0) + 120.0,
            (float) $afterToday['amount'],
            0.001,
            '每日趋势金额口径与产品维度一致'
        );
        $this->assertSame(
            (int) ($baselineToday['count'] ?? 0) + 1,
            (int) $afterToday['count'],
            '每日趋势笔数口径与产品维度一致'
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'email' => 'revenue-scope-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'revenue-scope-tester',
            'total_sales_amount' => 0,
        ], $attributes));
    }

    private function makeInvoice(
        User $user,
        int $status,
        string $amount,
        mixed $paidAt = null,
        ?string $productSpecSnapshot = null,
        string $type = InvoiceType::NEW_PURCHASE,
    ): Invoice {
        return Invoice::query()->create([
            'invoice_no' => 'INV-REV-'.uniqid(),
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'paid_amount' => $status === InvoiceStatus::PAID ? $amount : '0.00',
            'status' => $status,
            'paid_at' => $paidAt,
            'product_spec_snapshot' => $productSpecSnapshot,
        ]);
    }
}
