<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 切换窗口一键编排（Runbook T0 第 4–11 步的自动化形态）：
 *
 *   php artisan finance:cutover-run
 *
 * 顺序执行：前置检查 → rebuild dry-run → rebuild --execute --fresh → 000013 外键重指
 * → 闸门复验 → 三方一致性只读校验（账户余额 == 批次合计 == 热备列）。
 *
 * 失败语义：任何一步非零退出立即停机并打印回滚指引——旧表全程未被本命令改动，
 * 数据零损失；新表可通过 --execute --fresh 重复重建。
 * 幂等：切换已完成的环境重跑本命令，各步骤自动识别为"已完成"并跳过/仅验证。
 *
 * 不包含在本命令内（保留人工）：php artisan down/up、备份、人工下单冒烟、
 * 收件箱重放、cron 暂停/恢复——见文档「切换 Runbook」。
 */
final class RunFinanceCutoverCommand extends Command
{
    protected $signature = 'finance:cutover-run
        {--skip-rebuild : 新表已重建（重放/补救场景），只补跑外键与校验}
        {--no-migrate : 不执行 000013（已确认手工执行过）}';

    protected $description = '切换窗口一键编排：重建账本 + 外键重指 + 闸门与一致性校验（失败即停，附回滚指引）';

    private const MIGRATION_000013 = 'database/migrations/2026_10_20_000013_repoint_recharge_record_foreign_keys.php';

    public function handle(): int
    {
        // ── 第 1 步：前置检查 ──────────────────────────────────────────────
        // T+7 归档（000016 改名）后旧表不存在：rebuild 已退役，本命令随之退役
        if (! Schema::hasTable('account_transactions')) {
            $this->error('旧台账 account_transactions 已归档：finance:cutover-run 已退役，新账本即唯一真源。');

            return self::FAILURE;
        }

        $oldLedgerRows = (int) DB::table('account_transactions')->count();
        $this->info(sprintf('[1/6] 前置检查：旧台账 %d 行（为 0 时 rebuild 会硬失败）', $oldLedgerRows));
        if ($oldLedgerRows === 0) {
            $this->error('旧台账为 0 行：疑似连错环境。终止。');

            return self::FAILURE;
        }

        $alreadyRebuilt = $this->financeMigrationApplied();
        $migrationApplied = $this->migration000013Applied();
        if ($alreadyRebuilt && $migrationApplied) {
            $this->warn('检测到本库已处于「重建完成 + 外键已重指」状态：各步骤将只做验证，不再写入。');
        }

        // ── 第 2 步：rebuild dry-run ───────────────────────────────────────
        $this->info('[2/6] rebuild dry-run（预览规模与校准）');
        $code = $this->artisanExec('finance:rebuild');
        if ($code !== self::SUCCESS) {
            return $this->halt('rebuild dry-run 失败');
        }

        // ── 第 3 步：rebuild --execute --fresh ─────────────────────────────
        if ($this->option('skip-rebuild') || $alreadyRebuilt) {
            $this->warn($this->option('skip-rebuild')
                ? '[3/6] 按参数跳过 rebuild（--skip-rebuild）'
                : '[3/6] 新表已有数据（已完成重建），跳过 rebuild');
        } else {
            $this->info('[3/6] rebuild --execute --fresh（七闸门任一 FAIL 自动回滚）');
            $code = $this->artisanExec('finance:rebuild', ['--execute' => true, '--fresh' => true]);
            if ($code !== self::SUCCESS) {
                return $this->halt('rebuild 执行失败（新表已回滚到重建前状态，旧表未动）');
            }
        }

        // ── 第 4 步：000013 外键重指 ───────────────────────────────────────
        if ($this->option('no-migrate')) {
            $this->warn('[4/6] 按参数跳过 000013（--no-migrate）');
        } elseif ($migrationApplied) {
            $this->warn('[4/6] 000013 已执行过，跳过');
        } else {
            $this->info('[4/6] migrate 000013（recharge_records 外键重指到 finance_ledger_entries）');
            $code = $this->artisanExec('migrate', ['--force' => true, '--path' => self::MIGRATION_000013]);
            if ($code !== self::SUCCESS) {
                return $this->halt('000013 执行失败（新表数据不可解析，请核对 finance_reconciliation_diffs 后重跑 rebuild）');
            }
        }

        // ── 第 5 步：闸门复验 ──────────────────────────────────────────────
        $this->info('[5/6] G1–G7 闸门复验（--verify-only，只读）');
        $code = $this->artisanExec('finance:rebuild', ['--verify-only' => true]);
        if ($code !== self::SUCCESS) {
            return $this->halt('闸门复验未全绿：请勿放行业务，按回滚预案处理');
        }

        // ── 第 6 步：三方一致性只读校验 ────────────────────────────────────
        $this->info('[6/6] 三方一致性校验（账户余额 == 批次合计 == 热备列）');
        $mismatch = $this->consistencyMismatchCount();
        if ($mismatch > 0) {
            $this->error(sprintf('三方一致性不一致 %d 户：终止，按回滚预案处理。', $mismatch));

            return self::FAILURE;
        }
        $this->info(sprintf('      不一致 0 户（%s 热备比对）', config('finance.hot_standby') ? '启用' : '已关闭，跳过热备列'));

        // ── 收尾：剩余人工步骤清单 ─────────────────────────────────────────
        $this->newLine();
        $this->info('✅ 编排完成。剩余人工步骤（见 Runbook）：');
        $this->line('  1. php artisan up（解除维护模式）');
        $this->line('  2. 人工下一单余额支付 + 一单后台退款，核对台账/红字账单/批次');
        $this->line('  3. php artisan finance:replay-cutover-inbox（重放窗口内回调）');
        $this->line('  4. .env 关闭 FINANCE_CUTOVER_INBOX → config:cache；恢复 cron');

        return self::SUCCESS;
    }

    /** 全库三方一致性只读校验；返回不一致户数。 */
    private function consistencyMismatchCount(): int
    {
        $hotStandby = (bool) config('finance.hot_standby', true);

        $query = DB::table('finance_accounts as fa')
            ->leftJoin(DB::raw('(SELECT account_id, SUM(remaining_amount) AS lots_sum FROM finance_funding_lots GROUP BY account_id) fl'), 'fl.account_id', '=', 'fa.id')
            ->when($hotStandby, function ($q): void {
                $q->leftJoin('user_accounts as ua', 'ua.user_id', '=', 'fa.user_id');
            })
            ->selectRaw('COUNT(*) as cnt')
            // 三方任一不等即计入（OR），不能 AND——AND 会漏掉"只有热备列漂移"的户。
            // 注意口径：三侧统一乘 100 转分后再比（lots_sum 是元，不乘 100 会变成
            // 「分 vs 元」恒真，27 户有余额用户全部误报）。
            ->where(function ($q) use ($hotStandby): void {
                $q->whereRaw('fa.available_amount * 100 <> COALESCE(fl.lots_sum * 100, 0)');
                if ($hotStandby) {
                    $q->orWhereRaw('ua.cash_balance * 100 <> fa.available_amount * 100');
                }
            });

        return (int) ($query->value('cnt') ?? 0);
    }

    private function migration000013Applied(): bool
    {
        if (! Schema::hasTable('migrations')) {
            return false;
        }

        return DB::table('migrations')
            ->where('migration', '2026_10_20_000013_repoint_recharge_record_foreign_keys')
            ->exists();
    }

    /**
     * 判断新账本是否已完成重建（迁移 run 台账存在且 ledger 非空）。
     */
    private function financeMigrationApplied(): bool
    {
        $hasRuns = Schema::hasTable('finance_migration_runs')
            && DB::table('finance_migration_runs')->where('phase', 'verify')->exists();

        return $hasRuns && DB::table('finance_ledger_entries')->exists();
    }

    /** 透传子命令输出（闸门表格实时可见），并返回其退出码。 */
    private function artisanExec(string $command, array $parameters = []): int
    {
        return (int) $this->call($command, $parameters);
    }

    private function halt(string $reason): int
    {
        $this->newLine();
        $this->error('⛔ 已停止：'.$reason);
        $this->line('回滚指引（旧表全程未被改动，数据零损失）：');
        $this->line('  1. 保持 php artisan down（业务不放行）');
        $this->line('  2. 核对失败输出；可修复后直接重跑 php artisan finance:cutover-run（幂等）');
        $this->line('  3. 放弃切换：php artisan migrate:rollback --path='.self::MIGRATION_000013.' --force（若 000013 已执行），新表可整体 DROP，旧表即全部状态');

        return self::FAILURE;
    }
}
