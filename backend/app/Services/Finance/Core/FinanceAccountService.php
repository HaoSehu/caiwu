<?php

declare(strict_types=1);

namespace App\Services\Finance\Core;

use App\Exceptions\BusinessException;
use App\Models\FinanceAccount;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\Finance\Core\Data\CashMutation;
use App\Support\Money;
use App\Support\UserBalanceCache;
use Illuminate\Support\Facades\DB;

/**
 * 唯一现金余额写口。
 *
 * 三件事必须在这一层完成，调用方不得绕开：
 * 1. 先锁 users 再锁 finance_accounts，锁序与旧链路一致，避免与取消/支付流程交叉死锁；
 * 2. 余额、台账、批次在同一事务提交，任一步失败整体回滚；
 * 3. 切换期热备 user_accounts.cash_balance，使代码回滚可立即回到旧内核。
 */
final class FinanceAccountService
{
    public function __construct(private readonly FinanceLedgerService $ledger) {}

    /**
     * 入账（充值、退款、调增、返利转余额）。
     *
     * @param  array<string, mixed>  $meta
     */
    public function credit(int $userId, int $amountCents, string $eventType, array $meta = []): CashMutation
    {
        if ($amountCents <= 0) {
            throw new BusinessException('入账金额必须大于零', 42200, 422);
        }

        return $this->mutate($userId, $amountCents, $eventType, $meta);
    }

    /**
     * 出账（账单支付、扣款、认证费）。
     *
     * @param  array<string, mixed>  $meta  allow_negative=true 时允许扣成负余额（仅存量兼容用）
     */
    public function debit(int $userId, int $amountCents, string $eventType, array $meta = []): CashMutation
    {
        if ($amountCents <= 0) {
            throw new BusinessException('出账金额必须大于零', 42200, 422);
        }

        return $this->mutate($userId, -$amountCents, $eventType, $meta);
    }

    /**
     * 撤销此前的出账：余额加回，原批次同步恢复。
     *
     * @param  array<string, mixed>  $meta
     */
    public function restore(
        int $userId,
        int $amountCents,
        string $eventType,
        string $sourceType,
        int $sourceId,
        array $meta = [],
    ): CashMutation {
        if ($amountCents <= 0) {
            throw new BusinessException('恢复金额必须大于零', 42200, 422);
        }

        return DB::transaction(function () use ($userId, $amountCents, $eventType, $sourceType, $sourceId, $meta): CashMutation {
            [$account, $balance] = $this->lockAccount($userId);
            $balanceAfter = $balance + $amountCents;

            $result = $this->ledger->restore(
                $account,
                $eventType,
                $amountCents,
                $balanceAfter,
                $sourceType,
                $sourceId,
                $meta,
            );

            $this->persistBalance($account, $balanceAfter);

            return new CashMutation(
                accountId: (int) $account->id,
                userId: $userId,
                changeCents: $amountCents,
                balanceCents: $balanceAfter,
                entryId: (int) $result['entry']->id,
                entryNo: (string) $result['entry']->entry_no,
            );
        });
    }

    /**
     * 退款入账后追认原消费血缘（evidence only）：把退款金额回填到原账单的消费映射。
     *
     * 退款走 credit 会新建一个 refund 批次，原始「这笔钱花在哪笔批次」的线索
     * 只能从原账单的 finance_consumptions 读；不追认的话，退款在账上看起来像新钱，
     * 无法回答「这笔退款对应哪笔原消费」。
     */
    public function markRefundedConsumptions(int $userId, string $sourceType, int $sourceId, int $amountCents): int
    {
        if ($amountCents <= 0) {
            return 0;
        }

        $account = $this->accountFor($userId);

        return $this->ledger->markRefundedConsumptions($account, $sourceType, $sourceId, $amountCents);
    }

    /**
     * 读取可用余额（整数分）。默认不加锁，仅用于展示与校验；写入路径必须加锁。
     */
    public function balanceCents(int $userId, bool $lock = false): int
    {
        $account = FinanceAccount::query()
            ->where('user_id', $userId)
            ->where('currency', 'CNY')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        return $account instanceof FinanceAccount ? Money::toCents($account->available_amount) : 0;
    }

    /**
     * 取（或创建）账户；加锁版本供同一事务内的后续读取复用。
     */
    public function accountFor(int $userId, bool $lock = false): FinanceAccount
    {
        $this->ensureAccountRow($userId);

        return FinanceAccount::query()
            ->where('user_id', $userId)
            ->where('currency', 'CNY')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function mutate(int $userId, int $changeCents, string $eventType, array $meta): CashMutation
    {
        return DB::transaction(function () use ($userId, $changeCents, $eventType, $meta): CashMutation {
            [$account, $balance] = $this->lockAccount($userId);
            $balanceAfter = $balance + $changeCents;
            $allowNegative = (bool) ($meta['allow_negative'] ?? false);

            if ($balanceAfter < 0 && ! $allowNegative) {
                throw new BusinessException('余额不足', 40900, 409);
            }

            if ($changeCents > 0) {
                $result = $this->ledger->credit($account, $eventType, $changeCents, $balanceAfter, $meta);
                $allocations = [[
                    'lot_id' => (int) $result['lot']->id,
                    'lot_no' => (string) $result['lot']->lot_no,
                    'consumption_id' => null,
                    'amount_cents' => $changeCents,
                ]];
                $entryId = (int) $result['entry']->id;
                $entryNo = (string) $result['entry']->entry_no;
            } else {
                $result = $this->ledger->debit(
                    $account,
                    $eventType,
                    -$changeCents,
                    $balanceAfter,
                    $meta,
                    (bool) ($meta['strict_lots'] ?? true),
                );
                $allocations = $result['allocations'];
                $entryId = (int) $result['entry']->id;
                $entryNo = (string) $result['entry']->entry_no;
            }

            $this->persistBalance($account, $balanceAfter);

            return new CashMutation(
                accountId: (int) $account->id,
                userId: $userId,
                changeCents: $changeCents,
                balanceCents: $balanceAfter,
                entryId: $entryId,
                entryNo: $entryNo,
                allocations: $allocations,
            );
        });
    }

    /**
     * 固定锁序：users → finance_accounts。
     *
     * @return array{0: FinanceAccount, 1: int}
     */
    private function lockAccount(int $userId): array
    {
        $user = User::query()->lockForUpdate()->find($userId);
        if ($user === null) {
            throw new BusinessException('会员不存在', 40400, 404);
        }

        $account = $this->accountFor($userId, true);

        return [$account, Money::toCents($account->available_amount)];
    }

    private function ensureAccountRow(int $userId): void
    {
        $now = date('Y-m-d H:i:s');

        FinanceAccount::query()->insertOrIgnore([
            'user_id' => $userId,
            'currency' => 'CNY',
            'available_amount' => '0.00',
            'frozen_amount' => '0.00',
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 余额落库：行锁 + version 条件更新构成双层保护。
     */
    private function persistBalance(FinanceAccount $account, int $balanceAfterCents): void
    {
        $version = (int) $account->version;
        $affected = FinanceAccount::query()
            ->whereKey((int) $account->id)
            ->where('version', $version)
            ->update([
                'available_amount' => Money::formatCents($balanceAfterCents),
                'version' => $version + 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ($affected !== 1) {
            throw new BusinessException('账户余额已被并发修改，请重试', 40900, 409);
        }

        $account->forceFill([
            'available_amount' => Money::formatCents($balanceAfterCents),
            'version' => $version + 1,
        ]);

        $this->syncHotStandby((int) $account->user_id, $balanceAfterCents);
    }

    /**
     * 切换期热备：同一事务内把余额同步回旧列，供代码回滚时旧内核继续可用。
     * T+7 删除 cash_balance 前须先关闭 finance.hot_standby。
     */
    private function syncHotStandby(int $userId, int $balanceAfterCents): void
    {
        if (! (bool) config('finance.hot_standby', true)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        UserAccount::query()->insertOrIgnore([
            'user_id' => $userId,
            'cash_balance' => '0.00',
            'credit_limit' => '0.00',
            'referral_frozen_balance' => '0.00',
            'referral_available_balance' => '0.00',
            'referral_pending_withdrawal_balance' => '0.00',
            'referral_withdrawn_balance' => '0.00',
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        UserAccount::query()->where('user_id', $userId)->update([
            'cash_balance' => Money::formatCents($balanceAfterCents),
            'updated_at' => $now,
        ]);

        UserBalanceCache::forget($userId);
    }
}
