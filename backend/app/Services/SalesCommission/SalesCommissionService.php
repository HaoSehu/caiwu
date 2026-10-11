<?php

declare(strict_types=1);

namespace App\Services\SalesCommission;

use App\Constants\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\SalesAttributionLog;
use App\Models\SalesCommission;
use App\Models\SalesCommissionPlan;
use App\Models\SalesCommissionProductRate;
use App\Models\SalesCommissionSettlement;
use App\Models\SalesCommissionTier;
use App\Models\User;
use App\Services\System\OperationLogService;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M5 内部销售提成计提/冲正/归属服务。
 *
 * 资金红线：计提/冲正/归属全程不动用户余额、不写 AccountTransaction、不改 Payment；
 * 结算发放为线下发放的管理登记制（见 SalesCommissionSettlementService）。
 * 与推广大使返利并存双算（互不抵扣），口径解耦、链路互不影响。
 */
class SalesCommissionService
{
    public function __construct(
        private readonly OperationLogService $operationLogService,
    ) {}

    /**
     * 账单支付成功后计提（挂点：InvoicePaidOrchestrator 支付成功链路，与推广返利同链路独立调用）。
     *
     * 幂等：sales_commissions.invoice_id 唯一约束 + firstOrCreate，重复回调直接 skip。
     * 费率优先级：产品费率（fixed_amount > rate）> 方案阶梯 > 无（不计提）。
     * 计提基数：basis=1 账单实付金额（含余额支付）/ basis=2 订单金额。
     */
    public function accrueForInvoice(Invoice $invoice, string $scene = 'payment'): ?SalesCommission
    {
        if ((int) $invoice->status !== InvoiceStatus::PAID) {
            return null;
        }

        $invoice->loadMissing(['user']);
        $buyer = $invoice->user;
        if (! $buyer instanceof User) {
            return null;
        }

        $salesOwnerAdminId = $buyer->sales_owner_admin_id !== null ? (int) $buyer->sales_owner_admin_id : 0;
        if ($salesOwnerAdminId <= 0) {
            return null;
        }

        $lockKey = "lock:sales-commission:accrue:invoice:{$invoice->id}";

        return Cache::lock($lockKey, 30)->block(5, function () use ($invoice, $scene, $salesOwnerAdminId, $buyer): ?SalesCommission {
            // 幂等守卫：invoice 粒度唯一，重复回调直接复用既有记录
            $existing = SalesCommission::query()->where('invoice_id', (int) $invoice->id)->first();
            if ($existing instanceof SalesCommission) {
                return $existing;
            }

            $plan = $this->resolvePlan();
            if ($plan === null) {
                return null;
            }

            $baseAmount = $this->resolveBaseAmount($invoice, (int) $plan['basis']);
            if ($baseAmount <= 0) {
                return null;
            }

            $resolved = $this->resolveRate($plan, $invoice->product_id !== null ? (int) $invoice->product_id : null, $baseAmount);
            if ($resolved === null) {
                return null;
            }

            $commissionAmount = $this->resolveCommissionAmount($baseAmount, $resolved);

            try {
                return DB::transaction(function () use ($invoice, $scene, $salesOwnerAdminId, $buyer, $plan, $baseAmount, $resolved, $commissionAmount): SalesCommission {
                    return SalesCommission::query()->firstOrCreate(
                        ['invoice_id' => (int) $invoice->id],
                        [
                            'admin_user_id' => $salesOwnerAdminId,
                            'user_id' => (int) $buyer->id,
                            'order_id' => $invoice->order_id !== null ? (int) $invoice->order_id : null,
                            'plan_id' => (int) $plan['id'],
                            'plan_snapshot' => [
                                'plan_id' => (int) $plan['id'],
                                'plan_name' => (string) $plan['name'],
                                'basis' => (int) $plan['basis'],
                                'rate' => (float) $resolved['rate'],
                                'fixed_amount' => $resolved['fixed_amount'] !== null ? (float) $resolved['fixed_amount'] : null,
                                'tier_id' => $resolved['tier_id'] !== null ? (int) $resolved['tier_id'] : null,
                                'scene' => $scene,
                            ],
                            'base_amount' => Money::format($baseAmount),
                            'rate' => Money::format($resolved['rate']),
                            'commission_amount' => Money::format($commissionAmount),
                            'status' => SalesCommission::STATUS_PENDING,
                        ],
                    );
                });
            } catch (Throwable $exception) {
                // 并发穿透唯一约束（firstOrCreate 与 DB 竞态）：按幂等 skip 处理
                $alreadyExists = SalesCommission::query()->where('invoice_id', (int) $invoice->id)->first();
                if ($alreadyExists instanceof SalesCommission) {
                    return $alreadyExists;
                }

                Log::error('[销售提成] 计提落库失败', [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);

                return null;
            }
        });
    }

    /**
     * 账单退款/取消后的提成冲正：
     * - status=0/1（待结算/已入批次未发放）→ status=3 已取消（自动冲正）；
     *   已入批次的同步扣减批次登记总额/笔数（纯登记调整，不动资金）；
     * - status=2（已发放）→ 不改状态，仅记 activity log 提示线下追偿。
     */
    public function markReversed(Invoice $invoice, string $reason): void
    {
        $commissions = SalesCommission::query()->where('invoice_id', (int) $invoice->id)->get();

        foreach ($commissions as $commission) {
            if ((int) $commission->status === SalesCommission::STATUS_SETTLED) {
                $this->operationLogService->write(
                    userId: (int) $commission->admin_user_id,
                    userType: 'admin',
                    action: 'sales_commission.reversed',
                    module: 'sales_commission',
                    targetId: (int) $commission->id,
                    detail: [
                        'invoice_id' => (int) $invoice->id,
                        'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                        'commission_amount' => Money::format((float) $commission->commission_amount),
                        'status' => '已发放，提成不自动冲账，请线下追偿',
                        'reason' => $reason,
                    ],
                );

                continue;
            }

            if (! in_array((int) $commission->status, [SalesCommission::STATUS_PENDING, SalesCommission::STATUS_IN_BATCH], true)) {
                continue;
            }

            DB::transaction(function () use ($commission, $invoice, $reason): void {
                $locked = SalesCommission::query()->lockForUpdate()->findOrFail((int) $commission->id);
                if (! in_array((int) $locked->status, [SalesCommission::STATUS_PENDING, SalesCommission::STATUS_IN_BATCH], true)) {
                    return;
                }

                $locked->forceFill(['status' => SalesCommission::STATUS_CANCELLED])->save();

                if ((int) $locked->settlement_id !== 0 && $locked->settlement_id !== null) {
                    $settlement = SalesCommissionSettlement::query()->lockForUpdate()->find((int) $locked->settlement_id);
                    if ($settlement !== null) {
                        $remaining = max(Money::round($settlement->total_amount) - Money::round($locked->commission_amount), 0.0);
                        $settlement->forceFill([
                            'total_amount' => Money::format($remaining),
                            'commission_count' => max((int) $settlement->commission_count - 1, 0),
                        ])->save();
                    }
                }

                $this->operationLogService->write(
                    userId: (int) $locked->admin_user_id,
                    userType: 'system',
                    action: 'sales_commission.reversed',
                    module: 'sales_commission',
                    targetId: (int) $locked->id,
                    detail: [
                        'invoice_id' => (int) $invoice->id,
                        'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                        'commission_amount' => Money::format((float) $locked->commission_amount),
                        'status' => '已取消',
                        'reason' => $reason,
                    ],
                );
            });
        }
    }

    /**
     * 设置客户归属销售（同一归属幂等跳过）；归属变更写 attribution log 留痕。
     *
     * @return bool 是否实际发生变更
     */
    public function setSalesOwner(User $user, ?int $adminId, string $reason, int $operatorId): bool
    {
        $nextOwner = $adminId !== null && $adminId > 0 ? $adminId : null;

        if ($nextOwner !== null) {
            throw_if(
                AdminUser::query()->whereKey($nextOwner)->doesntExist(),
                new BusinessException('指定的销售（管理员）不存在'),
            );
        }

        $currentOwner = null;
        $changed = DB::transaction(function () use (&$currentOwner, $user, $nextOwner, $reason, $operatorId): bool {
            $locked = User::query()->lockForUpdate()->findOrFail((int) $user->id);

            // 幂等判定与留痕均以锁内真值为准（调用方持有的实例可能已过期）。
            $currentOwner = $locked->sales_owner_admin_id !== null ? (int) $locked->sales_owner_admin_id : null;
            if ($currentOwner === $nextOwner) {
                return false;
            }

            $locked->forceFill([
                'sales_owner_admin_id' => $nextOwner,
                'sales_owner_assigned_at' => now(),
            ])->save();

            SalesAttributionLog::query()->create([
                'user_id' => (int) $locked->id,
                'from_admin_id' => $currentOwner,
                'to_admin_id' => $nextOwner,
                'reason' => trim($reason) !== '' ? trim($reason) : null,
                'operator_id' => $operatorId > 0 ? $operatorId : null,
            ]);

            return true;
        });

        if (! $changed) {
            return false;
        }

        $this->operationLogService->write(
            userId: $operatorId,
            userType: 'admin',
            action: 'sales_commission.owner_assigned',
            module: 'sales_commission',
            targetId: (int) $user->id,
            detail: [
                'user_id' => (int) $user->id,
                'from_admin_id' => $currentOwner,
                'to_admin_id' => $nextOwner,
                'reason' => $reason,
            ],
        );

        return true;
    }

    /**
     * 费率解算（供计提与测试）：产品费率（fixed_amount > rate）> 阶梯 > null。
     *
     * @param  array<string, mixed>  $plan  方案数组（含 id / basis / name）
     * @return array{rate: float, fixed_amount: float|null, tier_id: int|null}|null
     */
    public function resolveRate(array $plan, ?int $productId, float $baseAmount): ?array
    {
        $planId = (int) ($plan['id'] ?? 0);
        if ($planId <= 0) {
            return null;
        }

        if ($productId !== null && $productId > 0) {
            $productRate = SalesCommissionProductRate::query()
                ->where('plan_id', $planId)
                ->where('product_id', $productId)
                ->first();

            if ($productRate !== null) {
                $fixedAmount = $productRate->fixed_amount !== null ? Money::round($productRate->fixed_amount) : null;
                if ($fixedAmount !== null) {
                    return ['rate' => 0.00, 'fixed_amount' => $fixedAmount, 'tier_id' => null];
                }

                if ($productRate->rate !== null) {
                    return ['rate' => Money::round($productRate->rate), 'fixed_amount' => null, 'tier_id' => null];
                }
            }
        }

        $tiers = SalesCommissionTier::query()
            ->where('plan_id', $planId)
            ->orderBy('min_amount')
            ->get();

        foreach ($tiers as $tier) {
            if ($tier->coversAmount($baseAmount)) {
                return ['rate' => Money::round($tier->rate), 'fixed_amount' => null, 'tier_id' => (int) $tier->id];
            }
        }

        return null;
    }

    /**
     * 命中「启用中且有效期覆盖今天」的最新方案（取最新启用一条）。
     *
     * @return array<string, mixed>|null
     */
    private function resolvePlan(): ?array
    {
        $plan = SalesCommissionPlan::query()
            ->enabled()
            ->orderByDesc('id')
            ->get()
            ->first(fn (SalesCommissionPlan $candidate): bool => $candidate->coversDate(now()));

        if ($plan === null) {
            return null;
        }

        return [
            'id' => (int) $plan->id,
            'name' => (string) $plan->name,
            'basis' => (int) $plan->basis,
        ];
    }

    /**
     * 计提基数：basis=1 账单实付金额（paid_amount，含余额支付，退化用 amount 兜底）/ basis=2 订单金额。
     * 两种口径均扣减已发生的退款标记金额（refund_amount），下限 0——防止退款标记后
     * 重放支付回调时按全额补计提。
     */
    private function resolveBaseAmount(Invoice $invoice, int $basis): float
    {
        $refundAmount = Money::round($invoice->refund_amount ?? 0);

        if ($basis === SalesCommissionPlan::BASIS_ORDER_AMOUNT) {
            return max(Money::round($invoice->amount) - $refundAmount, 0.0);
        }

        $paidAmount = Money::round($invoice->paid_amount);
        if ($paidAmount <= 0) {
            $paidAmount = Money::round($invoice->amount);
        }

        return max($paidAmount - $refundAmount, 0.0);
    }

    /**
     * 提成金额：固定金额优先，否则基数 × 比例 / 100（先乘后除再舍入，与推广返利口径一致）。
     *
     * @param  array{rate: float, fixed_amount: float|null, tier_id: int|null}  $resolved
     */
    private function resolveCommissionAmount(float $baseAmount, array $resolved): float
    {
        if ($resolved['fixed_amount'] !== null) {
            return Money::round($resolved['fixed_amount']);
        }

        return Money::round(Money::round($baseAmount * $resolved['rate']) / 100);
    }
}
