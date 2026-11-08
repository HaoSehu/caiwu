<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\ServiceStatus;
use App\Models\FinanceRefund;
use App\Models\Invoice;
use App\Models\ReferralWithdrawal;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** 服务即将到期的观察窗口（天） */
    private const EXPIRING_SOON_DAYS = 7;

    private const OVERVIEW_STATS_CACHE_KEY = 'dashboard:overview:stats';

    private const OVERVIEW_STATS_CACHE_TTL_SECONDS = 60;

    /**
     * 收入净额 SQL（退款标记批 2026-10-11）：实收 − 已完成退款累计（refunds 表口径，
     * 纯标记与真退款统一），GREATEST 防负。依赖 salesIncomeInvoices() 的
     * invoice_refund_totals 左连接与 invoices.* 全表前缀。
     */
    private const NET_INCOME_SQL = 'GREATEST(COALESCE(NULLIF(invoices.paid_amount, 0), invoices.amount, 0) - COALESCE(invoice_refund_totals.refunded_total, 0), 0)';

    public function stats(): array
    {
        return Cache::remember(
            self::OVERVIEW_STATS_CACHE_KEY,
            now()->addSeconds(self::OVERVIEW_STATS_CACHE_TTL_SECONDS),
            fn () => $this->buildOverviewStats()
        );
    }

    private function buildOverviewStats(): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $month = now()->startOfMonth();

        $userStats = User::query()
            ->selectRaw('COUNT(*) as total_users')
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as today_new_users', [$today])
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as month_new_users', [$month])
            ->first();

        $invoiceCountStats = Invoice::query()
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as today_new_invoices', [$today])
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as month_new_invoices', [$month])
            ->first();

        $serviceStats = Service::query()
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END), 0) as active_services')
            ->first();

        $ticketStats = Ticket::query()
            ->selectRaw('COALESCE(SUM(CASE WHEN status IN (0, 1, 2) THEN 1 ELSE 0 END), 0) as open_tickets')
            ->first();

        $invoiceIncomeStats = $this->salesIncomeInvoices()
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.status = ? AND invoices.paid_at >= ? THEN '.self::NET_INCOME_SQL.' ELSE 0 END), 0) as today_income', [InvoiceStatus::PAID, $today])
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.status = ? AND invoices.paid_at >= ? THEN '.self::NET_INCOME_SQL.' ELSE 0 END), 0) as month_income', [InvoiceStatus::PAID, $month])
            ->first();

        // 昨日同口径数据用于环比；独立 count/sum 查询，避免与上方聚合复用造成口径漂移
        $yesterdayNewUsers = User::query()
            ->where('created_at', '>=', $yesterday)
            ->where('created_at', '<', $today)
            ->count();

        $yesterdayNewInvoices = Invoice::query()
            ->where('created_at', '>=', $yesterday)
            ->where('created_at', '<', $today)
            ->count();

        $yesterdayIncome = (float) $this->salesIncomeInvoices()
            ->where('invoices.status', InvoiceStatus::PAID)
            ->where('invoices.paid_at', '>=', $yesterday)
            ->where('invoices.paid_at', '<', $today)
            ->sum(DB::raw(self::NET_INCOME_SQL));

        $expiringDeadline = now()->addDays(self::EXPIRING_SOON_DAYS);

        return [
            'counts' => [
                'total_users' => (int) ($userStats?->total_users ?? 0),
                'total_invoices' => (int) ($invoiceCountStats?->total_invoices ?? 0),
                'active_services' => (int) ($serviceStats?->active_services ?? 0),
            ],
            'todos' => [
                'unpaid_invoices' => Invoice::query()->where('status', InvoiceStatus::UNPAID)->count(),
                'open_tickets' => (int) ($ticketStats?->open_tickets ?? 0),
                'pending_verifications' => User::query()
                    ->where('verification_status', User::VERIFICATION_STATUS_PENDING_REVIEW)
                    ->count(),
                'pending_withdrawals' => ReferralWithdrawal::query()
                    ->where('status', ReferralWithdrawal::STATUS_PENDING)
                    ->count(),
            ],
            'services' => [
                'total' => Service::query()->count(),
                'expiring_soon' => Service::query()
                    ->where('status', ServiceStatus::ACTIVE)
                    ->where('expires_at', '>=', now())
                    ->where('expires_at', '<=', $expiringDeadline)
                    ->count(),
            ],
            'today' => [
                'new_users' => (int) ($userStats?->today_new_users ?? 0),
                'new_invoices' => (int) ($invoiceCountStats?->today_new_invoices ?? 0),
                'income' => (float) ($invoiceIncomeStats?->today_income ?? 0),
            ],
            'yesterday' => [
                'new_users' => $yesterdayNewUsers,
                'new_invoices' => $yesterdayNewInvoices,
                'income' => $yesterdayIncome,
            ],
            'month' => [
                'income' => (float) ($invoiceIncomeStats?->month_income ?? 0),
                'new_users' => (int) ($userStats?->month_new_users ?? 0),
                'new_invoices' => (int) ($invoiceCountStats?->month_new_invoices ?? 0),
            ],
        ];
    }

    public function monthlyRevenue(): array
    {
        return Cache::remember(
            'dashboard:overview:monthly_revenue',
            now()->addSeconds(self::OVERVIEW_STATS_CACHE_TTL_SECONDS),
            fn () => $this->buildMonthlyRevenue()
        );
    }

    private function buildMonthlyRevenue(): array
    {
        $month = now()->startOfMonth();
        $daysInMonth = (int) now()->format('t');

        // 本月已付账单按产品归一度量（同每日趋势的 paid_at 口径），金额按整数分累加避免浮点误差
        $allProducts = $this->salesIncomeInvoices()
            ->where('invoices.status', InvoiceStatus::PAID)
            ->where('invoices.paid_at', '>=', $month)
            ->where('invoices.paid_at', '<', $month->copy()->addMonth())
            ->selectRaw('
                COALESCE(NULLIF(invoices.product_spec_snapshot, ""), "未知产品") as product_name,
                ROUND(COALESCE(SUM('.self::NET_INCOME_SQL.'), 0) * 100) as total_amount_cents,
                COUNT(*) as count
            ')
            ->groupByRaw('COALESCE(NULLIF(invoices.product_spec_snapshot, ""), "未知产品")')
            ->orderByDesc('total_amount_cents')
            ->get();

        $topProducts = $allProducts->take(8);
        $otherCents = (int) $allProducts->skip(8)->sum('total_amount_cents');

        $revenueByProduct = $topProducts
            ->map(function ($row) {
                return [
                    'label' => (string) ($row->product_name ?: '未知产品'),
                    'amount' => $this->centsToAmount((int) $row->total_amount_cents),
                    'count' => (int) $row->count,
                ];
            })
            ->values()
            ->all();

        // 有剩余产品且有净值（含负的退款冲抵）时才追加"其他"分组
        if ($allProducts->count() > 8 && $otherCents !== 0) {
            $revenueByProduct[] = [
                'label' => '其他',
                'amount' => $this->centsToAmount($otherCents),
                'count' => (int) $allProducts->skip(8)->sum('count'),
            ];
        }

        // 本月每日已付金额趋势
        $dailyPaid = $this->salesIncomeInvoices()
            ->where('invoices.status', InvoiceStatus::PAID)
            ->where('invoices.paid_at', '>=', $month)
            ->where('invoices.paid_at', '<', $month->copy()->addMonth())
            ->selectRaw('DATE(invoices.paid_at) as date, ROUND(COALESCE(SUM('.self::NET_INCOME_SQL.'), 0) * 100) as daily_amount_cents, COUNT(*) as daily_count')
            ->groupByRaw('DATE(invoices.paid_at)')
            ->get()
            ->keyBy('date');

        // 本月每日新增用户，与收入趋势同轴对比经营与增长
        $dailyNewUsers = User::query()
            ->where('created_at', '>=', $month)
            ->where('created_at', '<', $month->copy()->addMonth())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as new_user_count')
            ->groupByRaw('DATE(created_at)')
            ->pluck('new_user_count', 'date')
            ->all();

        $dailyRevenue = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = $month->copy()->addDays($d - 1)->format('Y-m-d');
            $dailyRevenue[] = [
                'date' => $dateStr,
                'day' => $d,
                'amount' => $this->centsToAmount((int) ($dailyPaid[$dateStr]->daily_amount_cents ?? 0)),
                'count' => (int) ($dailyPaid[$dateStr]->daily_count ?? 0),
                'new_users' => (int) ($dailyNewUsers[$dateStr] ?? 0),
            ];
        }

        return [
            'revenue_by_product' => $revenueByProduct,
            'daily_revenue' => $dailyRevenue,
            'month_label' => now()->format('Y年n月'),
        ];
    }

    private function centsToAmount(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * 商品销售收入不包含充值、后台扣款和推荐奖励等资金流水。
     *
     * 退款口径（退款标记批 2026-10-11）：左连接已完成退款累计（refunds 表口径，
     * 纯标记与真退款统一），配合 NET_INCOME_SQL 净额扣减；红字账单（type=refund）
     * 本身就是退款的表达，与净额扣减互斥，排除出收入集合避免双计——
     * 部分真退款不再出现 X-2Y 重复扣减，全额真退款贡献 0 而非 -X。
     * 注意：加 join 后所有 invoices 列必须带表前缀（裸列会歧义或错账）。
     *
     * @return Builder<Invoice>
     */
    private function salesIncomeInvoices(): Builder
    {
        $refundTotals = FinanceRefund::completedTotalsQuery();

        return Invoice::query()
            ->leftJoinSub($refundTotals, 'invoice_refund_totals', 'invoice_refund_totals.invoice_id', '=', 'invoices.id')
            ->where(function (Builder $query): void {
                $query->whereNull('invoices.type')->orWhereNotIn('invoices.type', [
                    InvoiceType::RECHARGE,
                    InvoiceType::DEDUCTION,
                    InvoiceType::REFERRAL_CREDIT,
                    InvoiceType::REFUND,
                ]);
            });
    }
}
