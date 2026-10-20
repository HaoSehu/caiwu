<?php

declare(strict_types=1);

namespace App\Services\Finance\Core;

use App\Exceptions\BusinessException;
use App\Models\FinanceAccount;
use App\Models\FinanceConsumption;
use App\Models\FinanceFundingLot;
use App\Models\FinanceLedgerEntry;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * 资金批次分配器：出账按 FIFO 从最早的可用批次扣减，入账创建新批次。
 *
 * FIFO 顺序固定按批次主键升序（不按创建时间：历史回放存在空时间）。
 * 只取 remaining_amount > 0 的批次，负值校准批次天然被跳过。
 */
final class FundingLotAllocator
{
    /**
     * 为一笔出账分配批次并写入消费映射。
     *
     * @param  array{source_type?: string, source_id?: int}  $source
     * @return array{allocations: list<array{lot_id: int, lot_no: string, consumption_id: int, amount_cents: int}>, shortfall_cents: int}
     */
    public function allocate(
        FinanceAccount $account,
        int $amountCents,
        int $entryId,
        array $source,
        string $now,
        bool $strict = true,
    ): array {
        if ($amountCents <= 0) {
            throw new BusinessException('出账金额必须大于零', 42200, 422);
        }

        $remaining = $amountCents;
        $allocations = [];

        $lots = FinanceFundingLot::query()
            ->where('account_id', (int) $account->id)
            ->where('remaining_amount', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lots as $lot) {
            if ($remaining === 0) {
                break;
            }

            $available = Money::toCents($lot->remaining_amount);
            if ($available <= 0) {
                continue;
            }

            $used = min($remaining, $available);
            $lot->forceFill([
                'remaining_amount' => Money::formatCents($available - $used),
            ])->save();

            $consumption = FinanceConsumption::query()->create([
                'account_id' => (int) $account->id,
                'user_id' => (int) $account->user_id,
                'lot_id' => (int) $lot->id,
                'entry_id' => $entryId,
                'source_type' => $source['source_type'] ?? null,
                'source_id' => $source['source_id'] ?? null,
                'amount' => Money::formatCents($used),
                'restored_amount' => '0.00',
                'refunded_amount' => '0.00',
                'created_at' => $now,
            ]);

            $allocations[] = [
                'lot_id' => (int) $lot->id,
                'lot_no' => (string) $lot->lot_no,
                'consumption_id' => (int) $consumption->id,
                'amount_cents' => $used,
            ];
            $remaining -= $used;
        }

        if ($remaining !== 0 && $strict) {
            throw new BusinessException(
                '资金批次余额不足，无法说明该笔支出的资金来源',
                40900,
                409
            );
        }

        return ['allocations' => $allocations, 'shortfall_cents' => $remaining];
    }

    /**
     * 为入账创建批次，并回填台账的 funding_lot_id（写一次，之后不再变更）。
     */
    public function createLot(
        FinanceAccount $account,
        FinanceLedgerEntry $entry,
        int $amountCents,
        string $dataQuality,
        ?string $sourceType,
        ?int $sourceId,
        ?string $originType,
        ?int $originId,
        ?string $now = null,
    ): FinanceFundingLot {
        if ($amountCents === 0) {
            throw new BusinessException('入账金额不能为零', 42200, 422);
        }

        $lot = FinanceFundingLot::query()->create([
            'lot_no' => 'LT'.date('YmdHis').strtoupper(bin2hex(random_bytes(8))),
            'account_id' => (int) $account->id,
            'user_id' => (int) $account->user_id,
            'entry_id' => (int) $entry->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'origin_type' => $originType,
            'origin_id' => $originId,
            'amount' => Money::formatCents($amountCents),
            'remaining_amount' => Money::formatCents($amountCents),
            'data_quality' => $dataQuality,
            'created_at' => $now ?? date('Y-m-d H:i:s'),
        ]);

        $entry->forceFill(['funding_lot_id' => (int) $lot->id])->save();

        return $lot;
    }

    /**
     * 恢复一笔已发生的消费（预扣释放、支付取消）：批次剩余回补，不回补台账余额。
     *
     * 只处理尚未恢复完的消费；恢复金额不得超过剩余可恢复额度。
     *
     * @return array{restored_cents: int, entries: list<int>}
     */
    public function restoreBySource(
        FinanceAccount $account,
        string $sourceType,
        int $sourceId,
        int $amountCents,
        int $restoreEntryId,
        string $now,
    ): array {
        if ($amountCents <= 0) {
            throw new BusinessException('恢复金额必须大于零', 42200, 422);
        }

        $consumptions = FinanceConsumption::query()
            ->where('account_id', (int) $account->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $amountCents;
        $restoredTotal = 0;
        $touched = [];

        foreach ($consumptions as $consumption) {
            if ($remaining === 0) {
                break;
            }

            $consumed = Money::toCents($consumption->amount);
            $restored = Money::toCents($consumption->restored_amount);
            $restorable = $consumed - $restored;
            if ($restorable <= 0) {
                continue;
            }

            $take = min($remaining, $restorable);
            $lot = FinanceFundingLot::query()->lockForUpdate()->findOrFail((int) $consumption->lot_id);
            $lotRemaining = Money::toCents($lot->remaining_amount);
            $lotCredited = Money::toCents($lot->amount);

            if ($lotRemaining + $take > $lotCredited) {
                throw new BusinessException('批次恢复金额超过原始入账', 40900, 409);
            }

            $lot->forceFill(['remaining_amount' => Money::formatCents($lotRemaining + $take)])->save();
            $consumption->forceFill([
                'restored_amount' => Money::formatCents($restored + $take),
                'restored_entry_id' => $restoreEntryId,
            ])->save();

            $remaining -= $take;
            $restoredTotal += $take;
            $touched[] = (int) $consumption->id;
        }

        if ($remaining !== 0) {
            // 找不到对应消费（历史数据或测试预置余额）：剩余部分由调用方补记新批次，
            // 这里只返回已恢复额度，不抛错中断业务。
            return ['restored_cents' => $restoredTotal, 'entries' => $touched];
        }

        return ['restored_cents' => $restoredTotal, 'entries' => $touched];
    }

    /**
     * 退款入账后追认原消费血缘：把退款金额回填到「原账单的消费映射」上。
     *
     * 与 restoreBySource 的区别是语义：restore 表示「这笔钱从未花掉」（预扣释放/取消），
     * 会回补批次剩余；markRefunded 表示「这笔钱确实花掉了、事后退回」，
     * 只写证据链（consumptions.refunded_amount），不动批次剩余。
     *
     * 可追认额度 = 消费金额 − 已恢复 − 已追认，逐笔分摊，剩余部分不再硬凑
     * （历史缺口消费没有真实批次，追认不到就留空，不做假账）。
     *
     * @return int 实际追认的分（0 表示找不到可追认的原消费）
     */
    public function markRefundedBySource(
        FinanceAccount $account,
        string $sourceType,
        int $sourceId,
        int $amountCents,
    ): int {
        if ($amountCents <= 0) {
            return 0;
        }

        $consumptions = FinanceConsumption::query()
            ->where('account_id', (int) $account->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $amountCents;
        $markedTotal = 0;

        foreach ($consumptions as $consumption) {
            if ($remaining === 0) {
                break;
            }

            $cap = Money::toCents($consumption->amount)
                - Money::toCents($consumption->restored_amount)
                - Money::toCents($consumption->refunded_amount);

            if ($cap <= 0) {
                continue;
            }

            $take = min($cap, $remaining);
            $this->markRefunded($consumption, $take);

            $remaining -= $take;
            $markedTotal += $take;
        }

        return $markedTotal;
    }

    /**
     * 记录退款对原消费的追认（不回补批次剩余，仅用于证据链）。
     */
    public function markRefunded(FinanceConsumption $consumption, int $amountCents): void
    {
        $refunded = Money::toCents($consumption->refunded_amount);
        $cap = Money::toCents($consumption->amount) - Money::toCents($consumption->restored_amount);
        if ($refunded + $amountCents > $cap) {
            throw new BusinessException('退款追认金额超过该笔消费可追认额度', 40900, 409);
        }

        $consumption->forceFill([
            'refunded_amount' => Money::formatCents($refunded + $amountCents),
        ])->save();
    }

    /**
     * 校验账户的批次剩余合计是否等于可用余额（整数分）。
     */
    public function remainingTotalCents(int $accountId): int
    {
        $total = FinanceFundingLot::query()
            ->where('account_id', $accountId)
            ->sum(DB::raw('remaining_amount'));

        return Money::toCents($total);
    }
}
