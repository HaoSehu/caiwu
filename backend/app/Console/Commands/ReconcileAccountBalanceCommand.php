<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\LedgerConsistencyCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 在线账户余额对账。
 *
 * 对账 user_accounts 各余额字段与 account_transactions 流水中
 * 每个 (user_id, account_type) 最新一条 balance_after 快照是否一致。
 * 差异超过 0.01 元即视为不一致，输出差异行并写 warning 日志，
 * 作为生产环境余额并发/直接改库等异常的兜底发现手段。
 *
 * 与 ReconcileAccountDomainCommand 的区别：后者是迁移域一次性对账
 * （对比旧库 balance_logs 与新库 account_ledgers），依赖迁移上下文；
 * 本命令只对账当前在线库的 account_transactions 与 user_accounts，
 * 适合注册到 schedule:run 定时执行。
 *
 * 快照口径的具体查询由 App\Services\System\LedgerConsistencyCheck 共享提供，
 * finance:audit-ledger-consistency 的合计口径核对也复用同一服务。
 */
class ReconcileAccountBalanceCommand extends Command
{
    protected $signature = 'reconcile:account-balance {--json : 以 JSON 输出差异详情}';

    protected $description = '对账 account_transactions 流水快照与 user_accounts 余额字段，发现不一致即告警';

    public function __construct(
        private readonly LedgerConsistencyCheck $ledgerConsistencyCheck,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $diffRows = $this->ledgerConsistencyCheck->snapshotBalanceDiffs();

        $summary = [
            'checked_at' => now()->toDateTimeString(),
            'diff_count' => count($diffRows),
            'rows' => array_map(static function (object $row): array {
                return [
                    'user_id' => (int) $row->user_id,
                    'account_type' => (string) $row->account_type,
                    'latest_balance_after' => number_format((float) $row->latest_balance_after, 2, '.', ''),
                    'account_balance' => number_format((float) $row->account_balance, 2, '.', ''),
                    'diff' => number_format((float) $row->diff, 2, '.', ''),
                ];
            }, $diffRows),
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info(sprintf('对账完成：差异用户账户数 %d', $summary['diff_count']));
            if ($summary['diff_count'] > 0) {
                $this->table(
                    ['user_id', 'account_type', 'latest_balance_after', 'account_balance', 'diff'],
                    $summary['rows']
                );
            }
        }

        if ($summary['diff_count'] > 0) {
            Log::warning('[账户对账] 发现 account_transactions 流水快照与 user_accounts 余额不一致', $summary);
        }

        return $summary['diff_count'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
