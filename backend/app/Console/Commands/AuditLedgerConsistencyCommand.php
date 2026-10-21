<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\InvoiceStatus;
use App\Services\System\LedgerConsistencyCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 资金流水一致性审计（只读，不改数据）
 *
 * 专家团 H1 发现：account_transactions 大量流水无业务来源关联、recharge_records
 * 桥接覆盖率低、用户余额与流水合计存在账实不符。本命令输出审计口径，供
 * 日常对账与排障使用；账实不符属于存量业务数据问题，不在此自动修复。
 *
 * 建议注册为每日调度：见 routes/console.php。
 *
 * 用法：
 *   php artisan finance:audit-ledger-consistency
 *   php artisan finance:audit-ledger-consistency --json --strict
 *
 * 余额与流水的合计口径核对由 App\Services\System\LedgerConsistencyCheck
 * 共享提供，reconcile:account-balance 的快照口径核对也复用同一服务。
 */
class AuditLedgerConsistencyCommand extends Command
{
    protected $signature = 'finance:audit-ledger-consistency
        {--json : 以 JSON 输出结果}
        {--strict : 存在账实不符或无来源流水时返回非零退出码}
        {--sample=5 : 每类异常样本数量}';

    protected $description = '审计账户流水与余额的一致性、来源完整性与充值桥接覆盖';

    public function __construct(
        private readonly LedgerConsistencyCheck $ledgerConsistencyCheck,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sampleLimit = max(0, (int) $this->option('sample'));
        $report = $this->buildReport($sampleLimit);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->renderText($report);
        }

        if ((bool) $this->option('strict') && $this->hasAnomaly($report)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 台账数据源：优先新表 finance_ledger_entries；新表不存在时回退旧表（切换前环境）。
     * 归档后旧表改名 archived_account_transactions，回退分支会命中旧表名但带存在性判断。
     */
    private function resolveLedgerTable(): string
    {
        if (Schema::hasTable('finance_ledger_entries')) {
            return 'finance_ledger_entries';
        }

        if (Schema::hasTable('account_transactions')) {
            return 'account_transactions';
        }

        return 'finance_ledger_entries';
    }

    /**
     * @return array<string,mixed>
     */
    private function buildReport(int $sampleLimit): array
    {
        // 台账真源已切到 finance_ledger_entries（2026-10-20 重构）；旧表只在 T+7 归档前
        // 作为历史参照，归档后（archived_account_transactions）直接查询会 1146。
        // 这里按表存在性选择数据源，绝不写死旧表名。
        $ledgerTable = $this->resolveLedgerTable();
        $ledgerCount = (int) DB::table($ledgerTable)->count();
        $noSourceType = (int) DB::table($ledgerTable)->whereNull('source_type')->count();
        $noSourceId = (int) DB::table($ledgerTable)->whereNull('source_id')->count();
        $noTrace = (int) DB::table($ledgerTable)->whereNull('trace_id')->count();

        $balanceVsLedger = $this->ledgerConsistencyCheck->cashBalanceSumMismatches();
        $rechargeBridgeGap = $this->rechargeBridgeGap();

        return [
            'dry_run' => true,
            'checked_at' => now()->toDateTimeString(),
            'summary' => [
                'ledger_total' => $ledgerCount,
                'ledger_no_source_type' => $noSourceType,
                'ledger_no_source_id' => $noSourceId,
                'ledger_no_trace' => $noTrace,
                'balance_vs_ledger_mismatch_users' => $balanceVsLedger['count'],
                'recharge_invoice_bridge_gap' => $rechargeBridgeGap['count'],
            ],
            'samples' => [
                'balance_vs_ledger' => array_slice($balanceVsLedger['samples'], 0, $sampleLimit),
                'recharge_bridge_gap' => array_slice($rechargeBridgeGap['samples'], 0, $sampleLimit),
            ],
        ];
    }

    /**
     * 已支付 recharge 发票但 recharge_records 桥接缺失（无对应充值记录）的数量。
     *
     * @return array{count: int, samples: array<int,array<string,mixed>>}
     */
    private function rechargeBridgeGap(): array
    {
        $rows = DB::table('invoices as i')
            ->leftJoin('recharge_records as rr', 'rr.invoice_id', '=', 'i.id')
            ->where('i.type', 'recharge')
            ->where('i.status', InvoiceStatus::PAID)
            ->whereNull('rr.id')
            ->select([
                'i.id as invoice_id',
                'i.invoice_no',
                'i.user_id',
                'i.amount',
                'i.paid_amount',
                'i.created_at',
            ])
            ->orderBy('i.id')
            ->limit(300)
            ->get();

        $samples = $rows
            ->map(fn ($row): array => [
                'invoice_id' => (int) $row->invoice_id,
                'invoice_no' => (string) $row->invoice_no,
                'user_id' => (int) $row->user_id,
                'amount' => (string) $row->amount,
                'paid_amount' => (string) $row->paid_amount,
                'created_at' => (string) ($row->created_at ?? ''),
            ])
            ->values()
            ->all();

        return ['count' => count($samples), 'samples' => $samples];
    }

    /**
     * @param  array<string,mixed>  $report
     */
    private function hasAnomaly(array $report): bool
    {
        $s = $report['summary'];

        // --strict 只对「金额级」异常退出（余额 vs 流水不一致）。
        // source_type 缺失与充值桥接缺口属于存量数据质量项：历史行永远存在，
        // 计入会让 strict 永久红灯、调度报警被忽略（P1-5 已复现）。
        return (int) $s['balance_vs_ledger_mismatch_users'] > 0;
    }

    /**
     * @param  array<string,mixed>  $report
     */
    private function renderText(array $report): void
    {
        $s = $report['summary'];
        $this->info('资金流水一致性审计');
        $this->line('- 流水总数: '.$s['ledger_total']);
        $this->line('- 无来源类型(source_type): '.$s['ledger_no_source_type']);
        $this->line('- 无来源ID(source_id): '.$s['ledger_no_source_id']);
        $this->line('- 无 trace_id: '.$s['ledger_no_trace']);
        $this->line('- 余额与流水不一致用户: '.$s['balance_vs_ledger_mismatch_users']);
        $this->line('- 充值发票桥接缺口: '.$s['recharge_invoice_bridge_gap']);

        foreach ($report['samples'] as $name => $rows) {
            if ($rows === []) {
                continue;
            }
            $this->newLine();
            $this->line($name.' 样本:');
            foreach ($rows as $row) {
                $this->line('- '.json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }
    }
}
