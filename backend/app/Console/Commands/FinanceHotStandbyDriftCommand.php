<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserAccount;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 热备漂移对账（数据库结构分析报告 P1-4）。
 *
 * 切换期 finance.hot_standby=true 时，每笔资金写入都会在同一事务里同步
 * user_accounts.cash_balance；但历史上有「重建直写新表不走内核」的路径，
 * 且热备列没有 version 保护，一旦漂移没有任何检测手段。
 *
 * 本命令三方比对：finance_accounts.available_amount == finance_funding_lots 剩余合计
 * == user_accounts.cash_balance，统一转分比较，默认 dry-run 只报告。
 *
 * 退出码：有未解决的漂移就返回失败（含 --json 分支），否则调度/巡检拿不到告警信号；
 * --repair 只修热备列，修完仍存在的批次差异继续计为漂移。
 */
class FinanceHotStandbyDriftCommand extends Command
{
    protected $signature = 'finance:hot-standby-drift
        {--repair : 按 finance_accounts 真源回写热备列（不动台账与批次）}
        {--json : 以 JSON 输出}';

    protected $description = '三方对账：账户余额 / 批次剩余合计 / 热备列，默认只报告';

    public function handle(): int
    {
        // T+7 归档后 user_accounts.cash_balance 已被 000015 删除：列不存在时只比
        // 「账户余额 vs 批次剩余合计」，不再把热备列拼进 SQL（否则解析期直接报错）。
        $hasHotStandbyColumn = Schema::hasColumn('user_accounts', 'cash_balance');
        $hotStandbySelect = $hasHotStandbyColumn ? 'ua.cash_balance' : 'NULL AS cash_balance';
        $hotStandbyJoin = $hasHotStandbyColumn
            ? 'LEFT JOIN user_accounts ua ON ua.user_id = fa.user_id'
            : '';

        $rows = DB::select(<<<SQL
            SELECT
                fa.user_id,
                fa.available_amount,
                COALESCE(lots.lots_sum, 0) AS lots_sum,
                {$hotStandbySelect}
            FROM finance_accounts fa
            LEFT JOIN (
                SELECT account_id, SUM(remaining_amount) AS lots_sum
                FROM finance_funding_lots
                GROUP BY account_id
            ) lots ON lots.account_id = fa.id
            {$hotStandbyJoin}
            WHERE fa.currency = 'CNY'
        SQL);

        $drifts = [];
        foreach ($rows as $row) {
            $accountCents = Money::toCents($row->available_amount);
            $lotsCents = Money::toCents($row->lots_sum);
            $standbyCents = $row->cash_balance === null ? null : Money::toCents($row->cash_balance);

            $lotDrift = $accountCents !== $lotsCents;
            $standbyDrift = $standbyCents !== null && $standbyCents !== $accountCents;

            if (! $lotDrift && ! $standbyDrift) {
                continue;
            }

            $drifts[] = [
                'user_id' => (int) $row->user_id,
                'account' => Money::formatCents($accountCents),
                'lots' => Money::formatCents($lotsCents),
                'hot_standby' => $standbyCents === null ? null : Money::formatCents($standbyCents),
                'lot_drift' => $lotDrift,
                'standby_drift' => $standbyDrift,
            ];
        }

        $repairedUserIds = [];

        if ((bool) $this->option('repair') && $drifts !== []) {
            $repaired = 0;
            DB::transaction(function () use ($drifts, &$repaired, &$repairedUserIds): void {
                foreach ($drifts as $drift) {
                    if (! $drift['standby_drift']) {
                        continue;
                    }

                    $updated = UserAccount::query()
                        ->where('user_id', $drift['user_id'])
                        ->update([
                            'cash_balance' => $drift['account'],
                            'updated_at' => now(),
                        ]);

                    if ($updated === 1) {
                        $repaired++;
                        $repairedUserIds[] = $drift['user_id'];
                    }
                }
            });

            $this->warn(sprintf('已按账户真源回写 %d 个用户的热备列（批次差异需人工核查，不自动修复）。', $repaired));

            // 修复成功的纯热备漂移已消除，退出码只反映修复后仍存在的差异，避免手动修复后仍报失败。
            $drifts = array_values(array_filter(
                $drifts,
                static fn (array $drift): bool => ! ($drift['standby_drift']
                    && ! $drift['lot_drift']
                    && in_array($drift['user_id'], $repairedUserIds, true)),
            ));
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode(['drift_count' => count($drifts), 'rows' => $drifts], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $drifts === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($drifts === []) {
            $this->info('三方一致：账户余额 == 批次剩余合计 == 热备列。');

            return self::SUCCESS;
        }

        $this->table(
            ['user_id', '账户余额', '批次合计', '热备列', '批次差异', '热备差异'],
            array_map(static fn (array $row): array => [
                $row['user_id'], $row['account'], $row['lots'], $row['hot_standby'] ?? '(缺失)',
                $row['lot_drift'] ? 'YES' : '', $row['standby_drift'] ? 'YES' : '',
            ], $drifts),
        );

        $this->warn(sprintf('发现 %d 个用户存在漂移。加 --repair 可按账户真源回写热备列。', count($drifts)));

        return self::FAILURE;
    }
}
