<?php

declare(strict_types=1);

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * 资金账本一致性核对（只读，不改数据）。
 *
 * 集中提供 user_accounts 与 account_transactions 之间两种互补的核对口径，
 * 供定时对账与审计命令复用，避免命令层各自实现同一套 SQL：
 *  - 快照口径（snapshotBalanceDiffs）：每个 (user_id, account_type) 最新一条
 *    流水的 balance_after 应等于 user_accounts 对应余额字段，差异超过 0.01 元
 *    视为不一致，用于发现余额投影异常；
 *  - 合计口径（cashBalanceSumMismatches）：现金流水 SUM(change_amount) 合计应
 *    等于 user_accounts.cash_balance，同时覆盖"无任何现金流水但余额非零"的
 *    用户，用于发现账实不符。
 *
 * 服务只负责查询与差异发现；输出格式、告警日志与退出码语义由调用方决定。
 */
class LedgerConsistencyCheck
{
    /** account_type → user_accounts 余额字段的映射，界定快照口径的核对范围。 */
    private const ACCOUNT_TYPE_TO_COLUMN = [
        'cash' => 'cash_balance',
        'referral_frozen' => 'referral_frozen_balance',
        'referral_available' => 'referral_available_balance',
        'referral_pending_withdrawal' => 'referral_pending_withdrawal_balance',
        'referral_withdrawn' => 'referral_withdrawn_balance',
    ];

    /**
     * 快照口径：返回最新一条流水 balance_after 与 user_accounts 对应字段差异超过 0.01 的记录。
     *
     * 每行包含 user_id、account_type、latest_balance_after、account_balance、diff
     * （数值均已转为 float），按 user_id 与 account_type 升序排列。
     *
     * @return array<int, object>
     */
    public function snapshotBalanceDiffs(): array
    {
        $caseColumn = implode(' ', array_map(
            static fn (string $type, string $column): string => "WHEN '{$type}' THEN ua.{$column}",
            array_keys(self::ACCOUNT_TYPE_TO_COLUMN),
            array_values(self::ACCOUNT_TYPE_TO_COLUMN)
        ));

        $latestBalanceAfter = $this->latestBalanceAfterSubquery();

        // 外层包装以便在 WHERE 复用 ABS(...) > 0.01（MySQL 不允许直接在 HAVING 引用嵌套别名）。
        $rows = DB::select(
            "SELECT user_id, account_type, latest_balance_after, account_balance,
                    ROUND(ABS(latest_balance_after - account_balance), 2) AS diff
             FROM (
                 SELECT t.user_id,
                        t.account_type,
                        CAST(t.balance_after AS DECIMAL(20,2)) AS latest_balance_after,
                        CASE t.account_type {$caseColumn} ELSE NULL END AS account_balance
                 FROM account_transactions t
                 JOIN user_accounts ua ON ua.user_id = t.user_id
                 JOIN (
                     {$latestBalanceAfter}
                 ) latest ON latest.user_id = t.user_id AND latest.account_type = t.account_type AND latest.max_id = t.id
                 WHERE t.account_type IN ('cash','referral_frozen','referral_available','referral_pending_withdrawal','referral_withdrawn')
             ) AS computed
             WHERE account_balance IS NOT NULL
               AND ROUND(ABS(latest_balance_after - account_balance), 2) > 0.01
             ORDER BY user_id ASC, account_type ASC"
        );

        return array_map(static function (object $row): object {
            $row->user_id = (int) $row->user_id;
            $row->latest_balance_after = (float) $row->latest_balance_after;
            $row->account_balance = (float) $row->account_balance;
            $row->diff = (float) $row->diff;

            return $row;
        }, $rows);
    }

    /**
     * 合计口径：返回 cash_balance 与现金流水合计不一致的用户样本（含无现金流水的用户）。
     *
     * @param  int  $limit  样本最多返回条数
     * @return array{count: int, samples: array<int,array<string,mixed>>}
     */
    public function cashBalanceSumMismatches(int $limit = 200): array
    {
        $ledgerSub = DB::table('account_transactions')
            ->select('user_id', DB::raw('SUM(change_amount) AS ledger_sum'))
            ->where('account_type', 'cash')
            ->groupBy('user_id');

        $rows = DB::table('user_accounts as ua')
            ->leftJoinSub($ledgerSub, 'lt', 'lt.user_id', '=', 'ua.user_id')
            ->where(function ($query): void {
                $query->whereNull('lt.ledger_sum')
                    ->orWhereColumn('ua.cash_balance', '<>', 'lt.ledger_sum');
            })
            ->select([
                'ua.user_id',
                'ua.cash_balance',
                DB::raw('COALESCE(lt.ledger_sum, 0) AS ledger_sum'),
            ])
            ->orderBy('ua.user_id')
            ->limit($limit)
            ->get();

        $samples = $rows
            ->map(fn ($row): array => [
                'user_id' => (int) $row->user_id,
                'cash_balance' => (string) $row->cash_balance,
                'ledger_sum' => (string) $row->ledger_sum,
            ])
            ->values()
            ->all();

        return ['count' => count($samples), 'samples' => $samples];
    }

    /**
     * 每个 (user_id, account_type) 最新一条流水的 id 子查询，供快照口径 JOIN 取最新快照。
     */
    private function latestBalanceAfterSubquery(): string
    {
        return "SELECT user_id, account_type, MAX(id) AS max_id
                FROM account_transactions
                WHERE account_type IN ('cash','referral_frozen','referral_available','referral_pending_withdrawal','referral_withdrawn')
                GROUP BY user_id, account_type";
    }
}
