<?php

declare(strict_types=1);

namespace App\Services\Finance\Core\Data;

use App\Support\Money;

/**
 * 一次现金余额变更的结果快照。
 *
 * 全字段使用整数分，避免调用方再做元/分换算；落库与 API 展示请使用 balanceAmount()。
 */
final readonly class CashMutation
{
    /**
     * @param  list<array{lot_id: int, lot_no: string, consumption_id: int|null, amount_cents: int}>  $allocations
     */
    public function __construct(
        public int $accountId,
        public int $userId,
        public int $changeCents,
        public int $balanceCents,
        public int $entryId,
        public string $entryNo,
        public array $allocations = [],
    ) {}

    /** 变动后余额，两位小数元字符串。 */
    public function balanceAmount(): string
    {
        return Money::formatCents($this->balanceCents);
    }

    /** 本次变动额（带符号），两位小数元字符串。 */
    public function changeAmount(): string
    {
        return Money::formatCents($this->changeCents);
    }
}
