<?php

declare(strict_types=1);

namespace App\Services\Finance\Core;

use App\Models\FinanceAccount;
use App\Models\FinanceFundingLot;
use App\Models\FinanceLedgerEntry;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * 唯一台账写口。所有现金层级变动都必须经过这里，以冻结 event_type 口径、
 * balance_after 一致性与批次映射。
 *
 * 三类写入：
 * - credit：真实入账（充值/退款/调增），同事务创建一个新批次；
 * - debit：真实出账（支付/扣款），同事务按 FIFO 创建消费映射；
 * - restore：撤销此前的出账（预扣释放/支付取消），回补原批次，不创建新批次。
 *
 * 因此「正数台账必有一个批次」需精确表述为：正数台账要么 funding_lot_id 非空，
 * 要么被某条 consumption.restored_entry_id 指向。
 */
final class FinanceLedgerService
{
    public function __construct(private readonly FundingLotAllocator $lots) {}

    /**
     * 入账：写台账 + 创建批次。
     *
     * @param  array<string, mixed>  $meta
     * @return array{entry: FinanceLedgerEntry, lot: FinanceFundingLot}
     */
    public function credit(
        FinanceAccount $account,
        string $eventType,
        int $amountCents,
        int $balanceAfterCents,
        array $meta = [],
    ): array {
        $entry = $this->append($account, $eventType, $amountCents, $balanceAfterCents, $meta);

        $lot = $this->lots->createLot(
            $account,
            $entry,
            $amountCents,
            (string) ($meta['lot_data_quality'] ?? 'normal'),
            (string) ($meta['lot_source_type'] ?? ($meta['source_type'] ?? '')),
            isset($meta['lot_source_id']) ? (int) $meta['lot_source_id'] : (isset($meta['source_id']) ? (int) $meta['source_id'] : null),
            isset($meta['origin_type']) ? (string) $meta['origin_type'] : null,
            isset($meta['origin_id']) ? (int) $meta['origin_id'] : null,
            $this->createdAt($meta),
        );

        return ['entry' => $entry, 'lot' => $lot];
    }

    /**
     * 出账：写台账 + FIFO 消费映射。
     *
     * @param  array<string, mixed>  $meta
     * @return array{entry: FinanceLedgerEntry, allocations: list<array{lot_id: int, lot_no: string, consumption_id: int, amount_cents: int}>, shortfall_cents: int}
     */
    public function debit(
        FinanceAccount $account,
        string $eventType,
        int $amountCents,
        int $balanceAfterCents,
        array $meta = [],
        bool $strict = true,
    ): array {
        $entry = $this->append($account, $eventType, -$amountCents, $balanceAfterCents, $meta);

        $result = $this->lots->allocate(
            $account,
            $amountCents,
            (int) $entry->id,
            [
                'source_type' => isset($meta['source_type']) ? (string) $meta['source_type'] : null,
                'source_id' => isset($meta['source_id']) ? (int) $meta['source_id'] : null,
            ],
            $this->createdAt($meta),
            $strict,
        );

        return [
            'entry' => $entry,
            'allocations' => $result['allocations'],
            'shortfall_cents' => $result['shortfall_cents'],
        ];
    }

    /**
     * 撤销出账：写正数台账（不建新批次）+ 回补原批次。
     *
     * 若账单名下没有可恢复的消费（历史数据或预置余额），缺口部分补记一个
     * `legacy_restore` 批次，保证「批次剩余合计 == 账户余额」不被破坏。
     *
     * @param  array<string, mixed>  $meta
     * @return array{entry: FinanceLedgerEntry, restored_cents: int, fallback_cents: int}
     */
    public function restore(
        FinanceAccount $account,
        string $eventType,
        int $amountCents,
        int $balanceAfterCents,
        string $sourceType,
        int $sourceId,
        array $meta = [],
    ): array {
        $entry = $this->append($account, $eventType, $amountCents, $balanceAfterCents, $meta);

        $restored = $this->lots->restoreBySource(
            $account,
            $sourceType,
            $sourceId,
            $amountCents,
            (int) $entry->id,
            $this->createdAt($meta),
        );

        $fallbackCents = $amountCents - $restored['restored_cents'];
        if ($fallbackCents > 0) {
            $this->lots->createLot(
                $account,
                $entry,
                $fallbackCents,
                'legacy_restore',
                $sourceType,
                $sourceId,
                null,
                null,
                $this->createdAt($meta),
            );
        }

        return [
            'entry' => $entry,
            'restored_cents' => $restored['restored_cents'],
            'fallback_cents' => $fallbackCents,
        ];
    }

    /**
     * 退款入账后的原消费血缘追认（只管证据链，不动余额与批次剩余）。
     * 返回实际追认的分；0 表示原账单没有可追溯的消费映射（历史缺口）。
     */
    public function markRefundedConsumptions(
        FinanceAccount $account,
        string $sourceType,
        int $sourceId,
        int $amountCents,
    ): int {
        return $this->lots->markRefundedBySource($account, $sourceType, $sourceId, $amountCents);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function append(
        FinanceAccount $account,
        string $eventType,
        int $changeCents,
        int $balanceAfterCents,
        array $meta = [],
    ): FinanceLedgerEntry {
        $this->assertInTransaction();
        $now = $this->createdAt($meta);

        return FinanceLedgerEntry::query()->create([
            'entry_no' => 'FL'.date('YmdHis').strtoupper(bin2hex(random_bytes(8))),
            'account_id' => (int) $account->id,
            'user_id' => (int) $account->user_id,
            'account_type' => (string) ($meta['account_type'] ?? 'cash'),
            'event_type' => $eventType,
            'change_amount' => Money::formatCents($changeCents),
            'balance_after' => Money::formatCents($balanceAfterCents),
            'currency' => (string) ($account->currency ?? 'CNY'),
            'source_type' => isset($meta['source_type']) ? (string) $meta['source_type'] : null,
            'source_id' => isset($meta['source_id']) ? (int) $meta['source_id'] : null,
            // 旧行为保持一致：未显式给 origin 时回填业务来源，保证跨域溯源字段不空
            'origin_type' => isset($meta['origin_type'])
                ? (string) $meta['origin_type']
                : (isset($meta['source_type']) ? (string) $meta['source_type'] : null),
            'origin_id' => isset($meta['origin_id'])
                ? (int) $meta['origin_id']
                : (isset($meta['source_id']) ? (int) $meta['source_id'] : null),
            'operator_type' => isset($meta['operator_type']) ? (string) $meta['operator_type'] : null,
            'operator_id' => isset($meta['operator_id']) ? (int) $meta['operator_id'] : null,
            'operator_name' => isset($meta['operator_name']) ? (string) $meta['operator_name'] : null,
            'remark' => (string) ($meta['remark'] ?? ''),
            'trace_id' => isset($meta['trace_id']) ? (string) $meta['trace_id'] : null,
            'created_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function createdAt(array $meta): string
    {
        return (string) ($meta['created_at'] ?? date('Y-m-d H:i:s'));
    }

    /**
     * 台账写入必须在事务内：否则余额与批次可能只落一半。
     */
    public function assertInTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new \LogicException('资金台账写入必须在数据库事务内执行');
        }
    }
}
