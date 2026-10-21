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
 *
 * 退出码口径：默认只要发现差异就返回 FAILURE，便于人工巡检即时告警；
 * 调度执行时传 --tolerate-legacy，存量历史漂移（如 2026-10 财务内核切换前
 * 返利释放未写冻结侧冲减行留下的 referral_frozen 差异）只告警不失败，
 * 否则心跳会因无法自动修复的存量数据天天红灯、真正的告警被淹没。
 * 该容忍口径与 finance:audit-ledger-consistency 的 --strict 反向控制同源：
 * 只有可自愈的金额级异常才允许阻断调度（参见 AuditLedgerConsistencyCommand:160-163）。
 */
class ReconcileAccountBalanceCommand extends Command
{
    protected $signature = 'reconcile:account-balance
        {--json : 以 JSON 输出差异详情}
        {--tolerate-legacy : 存量漂移只告警不失败（调度用，人工巡检不要加）}';

    protected $description = '对账 account_transactions 流水快照与 user_accounts 余额字段，发现不一致即告警';

    public function __construct(
        private readonly LedgerConsistencyCheck $ledgerConsistencyCheck,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // 调度场景必须容忍存量漂移：历史路径（如返利释放未写冻结侧冲减行）留下的
        // 快照差异无法由本命令自动修复，若一律非零退出，心跳会天天报警并淹没真问题。
        // 人工巡检不要加 --tolerate-legacy，保持「有差异即失败」的强口径。
        $tolerateLegacy = (bool) $this->option('tolerate-legacy');
        $diffRows = $this->ledgerConsistencyCheck->snapshotBalanceDiffs();

        $summary = [
            'checked_at' => now()->toDateTimeString(),
            'tolerate_legacy' => $tolerateLegacy,
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

            if ($tolerateLegacy) {
                $this->warn(sprintf(
                    '存量漂移容忍已开启：%d 条快照差异只告警不失败，请人工复核后按需修复历史数据。',
                    $summary['diff_count']
                ));
            }
        }

        if ($summary['diff_count'] === 0) {
            return self::SUCCESS;
        }

        return $tolerateLegacy ? self::SUCCESS : self::FAILURE;
    }
}
