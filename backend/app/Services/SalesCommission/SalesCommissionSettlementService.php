<?php

declare(strict_types=1);

namespace App\Services\SalesCommission;

use App\Exceptions\BusinessException;
use App\Models\SalesCommission;
use App\Models\SalesCommissionSettlement;
use App\Services\System\OperationLogService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * M5 结算批次服务：线下发放的管理登记制。
 *
 * 资金红线（断言点）：create/approve/reject 绝不调用 AccountService /
 * FinanceLedgerWriter，不写 AccountTransaction，不动用户余额——
 * 结算发放仅是「线下已发放」事实的管理登记。
 */
class SalesCommissionSettlementService
{
    public function __construct(
        private readonly OperationLogService $operationLogService,
    ) {}

    /**
     * 创建结算批次：聚合该销售指定期间内 status=0（待结算）计提入批次。
     * 重叠期校验：同销售存在未闭环（待审核）且期间重叠的批次时拒绝。
     */
    public function create(int $adminUserId, string $periodStart, string $periodEnd, int $operatorId, ?string $remark = null): SalesCommissionSettlement
    {
        $adminUserId = (int) $adminUserId;
        $periodStart = date('Y-m-d', strtotime($periodStart));
        $periodEnd = date('Y-m-d', strtotime($periodEnd));

        throw_if($periodStart > $periodEnd, new BusinessException('结算期间起止不合法：开始日期不能晚于结束日期'));

        $overlapping = SalesCommissionSettlement::query()
            ->where('admin_user_id', $adminUserId)
            ->where('status', SalesCommissionSettlement::STATUS_PENDING)
            ->where('period_start', '<=', $periodEnd)
            ->where('period_end', '>=', $periodStart)
            ->exists();

        throw_if($overlapping, new BusinessException('该销售存在与本次期间重叠的待审核结算批次，请先处理后再创建'));

        return DB::transaction(function () use ($adminUserId, $periodStart, $periodEnd, $operatorId, $remark): SalesCommissionSettlement {
            $commissions = SalesCommission::query()
                ->where('admin_user_id', $adminUserId)
                ->where('status', SalesCommission::STATUS_PENDING)
                ->whereBetween('created_at', [$periodStart.' 00:00:00', $periodEnd.' 23:59:59'])
                ->lockForUpdate()
                ->get();

            throw_if($commissions->isEmpty(), new BusinessException('该期间内没有可结算的待结算提成'));

            $totalAmount = $commissions->sum(fn (SalesCommission $commission): float => Money::round($commission->commission_amount));

            $settlement = SalesCommissionSettlement::query()->create([
                'admin_user_id' => $adminUserId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total_amount' => Money::format($totalAmount),
                'commission_count' => $commissions->count(),
                'status' => SalesCommissionSettlement::STATUS_PENDING,
                'operator_id' => $operatorId > 0 ? $operatorId : null,
                'remark' => $remark !== null && trim($remark) !== '' ? trim($remark) : null,
            ]);

            SalesCommission::query()
                ->whereIn('id', $commissions->pluck('id'))
                ->where('status', SalesCommission::STATUS_PENDING)
                ->update([
                    'status' => SalesCommission::STATUS_IN_BATCH,
                    'settlement_id' => (int) $settlement->id,
                    'updated_at' => now(),
                ]);

            $this->operationLogService->write(
                userId: $operatorId,
                userType: 'admin',
                action: 'sales_commission.settlement.created',
                module: 'sales_commission',
                targetId: (int) $settlement->id,
                detail: [
                    'admin_user_id' => $adminUserId,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'total_amount' => Money::format($totalAmount),
                    'commission_count' => $commissions->count(),
                ],
            );

            return $settlement;
        });
    }

    /**
     * 批次审核发放（纯登记）：批次 → 已发放；逐条计提 → 已发放 + settled_at。
     * 资金红线：绝不调用 AccountService / FinanceLedgerWriter，不写资金台账。
     */
    public function approve(SalesCommissionSettlement $settlement, int $operatorId, ?string $remark = null): SalesCommissionSettlement
    {
        return DB::transaction(function () use ($settlement, $operatorId, $remark): SalesCommissionSettlement {
            $locked = SalesCommissionSettlement::query()->lockForUpdate()->findOrFail((int) $settlement->id);

            throw_if(
                (int) $locked->status !== SalesCommissionSettlement::STATUS_PENDING,
                new BusinessException('仅待审核批次支持发放确认'),
            );

            $locked->forceFill([
                'status' => SalesCommissionSettlement::STATUS_PAID,
                'operator_id' => $operatorId > 0 ? $operatorId : $locked->operator_id,
                'settled_at' => now(),
                'remark' => $remark !== null && trim($remark) !== '' ? trim($remark) : $locked->remark,
            ])->save();

            SalesCommission::query()
                ->where('settlement_id', (int) $locked->id)
                ->where('status', SalesCommission::STATUS_IN_BATCH)
                ->update([
                    'status' => SalesCommission::STATUS_SETTLED,
                    'settled_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->operationLogService->write(
                userId: $operatorId,
                userType: 'admin',
                action: 'sales_commission.settlement.approved',
                module: 'sales_commission',
                targetId: (int) $locked->id,
                detail: [
                    'admin_user_id' => (int) $locked->admin_user_id,
                    'total_amount' => Money::format((float) $locked->total_amount),
                    'commission_count' => (int) $locked->commission_count,
                    'note' => '线下发放登记，不动用户余额、不写资金台账',
                ],
            );

            return $locked;
        });
    }

    /**
     * 批次驳回：批次 → 已驳回；批次内计提（已入批次未发放）→ 回待结算并解绑批次。
     */
    public function reject(SalesCommissionSettlement $settlement, int $operatorId, ?string $remark = null): SalesCommissionSettlement
    {
        return DB::transaction(function () use ($settlement, $operatorId, $remark): SalesCommissionSettlement {
            $locked = SalesCommissionSettlement::query()->lockForUpdate()->findOrFail((int) $settlement->id);

            throw_if(
                (int) $locked->status !== SalesCommissionSettlement::STATUS_PENDING,
                new BusinessException('仅待审核批次支持驳回'),
            );

            $locked->forceFill([
                'status' => SalesCommissionSettlement::STATUS_REJECTED,
                'operator_id' => $operatorId > 0 ? $operatorId : $locked->operator_id,
                'remark' => $remark !== null && trim($remark) !== '' ? trim($remark) : $locked->remark,
            ])->save();

            SalesCommission::query()
                ->where('settlement_id', (int) $locked->id)
                ->where('status', SalesCommission::STATUS_IN_BATCH)
                ->update([
                    'status' => SalesCommission::STATUS_PENDING,
                    'settlement_id' => null,
                    'updated_at' => now(),
                ]);

            $this->operationLogService->write(
                userId: $operatorId,
                userType: 'admin',
                action: 'sales_commission.settlement.rejected',
                module: 'sales_commission',
                targetId: (int) $locked->id,
                detail: [
                    'admin_user_id' => (int) $locked->admin_user_id,
                    'total_amount' => Money::format((float) $locked->total_amount),
                    'commission_count' => (int) $locked->commission_count,
                    'remark' => (string) ($remark ?? ''),
                ],
            );

            return $locked;
        });
    }
}
