<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

/**
 * 「未付账单」聚合的单一口径：未付 = status 为待支付。
 * 历史状态 2/3 已被迁移收敛为已取消（4），此前台账汇总用 IN (0, 3)、
 * 账单汇总用 = 待支付 两种谓词各自实现，同名 unpaid_amount 口径写法漂移，
 * 这里收敛为单点，供 FinanceLedgerQueryService 与 ClientInvoicePaymentWorkflowService 共用。
 */
final class InvoiceUnpaidAggregate
{
    private function __construct() {}

    /**
     * 在 Invoice 聚合查询上追加统一的未付金额/未付笔数列。
     * 计数列别名仅由调用方硬编码常量传入（默认 unpaid_count；账单汇总沿用历史列名 unpaid 以保持响应契约）。
     *
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public static function applyUnpaidSelects(Builder $query, string $countColumn = 'unpaid_count'): Builder
    {
        return $query
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN amount - COALESCE(paid_amount, 0) ELSE 0 END), 0) as unpaid_amount',
                [InvoiceStatus::UNPAID]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as '.$countColumn,
                [InvoiceStatus::UNPAID]
            );
    }
}
