<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FinanceAccount;
use App\Models\FinanceConsumption;
use App\Models\FinanceFundingLot;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceMigrationRun;
use App\Models\FinanceOperation;
use App\Models\FinanceReconciliationDiff;
use App\Services\Finance\Core\FinanceLedgerService;
use App\Services\Finance\Core\FundingLotAllocator;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * 财务账本重建：把旧 account_transactions / refunds / user_accounts.cash_balance
 * 迁移到新表，并生成资金批次与消费映射。
 *
 * 默认 dry-run；--execute 才写库；--verify-only 只跑闸门。
 *
 * 三条不可违反的规则：
 * 1. 保留旧主键：recharge_records 两条外键依赖它平滑重指到新表；
 * 2. 不重算历史 balance_after：只复制原值，差额通过追加 calibration 行表达；
 * 3. 不为历史虚高消费伪造批次：分配不足只登记差异，不补假数据。
 */
final class RebuildFinanceLedgerCommand extends Command
{
    protected $signature = 'finance:rebuild
        {--execute : 实际写入新表；缺省为 dry-run}
        {--verify-only : 只执行 G1-G7 闸门校验，不写库}
        {--fresh : 写入前清空新表（仅演练与切换窗口使用）}
        {--allow-empty : 允许旧台账为空时继续（仅全新空库演练使用，生产窗口禁止）}
        {--chunk=500 : 分批大小}';

    protected $description = '把旧财务台账/退款/余额迁移到新账本（默认 dry-run）';

    private const PHASE_LABELS = [
        'snapshot' => '基线快照',
        'accounts' => '账户复制',
        'ledger' => '台账复制',
        'lots' => '批次回放',
        'calibration' => '存量校准',
        'refunds' => '退款迁移',
        'verify' => '闸门校验',
    ];

    public function handle(
        FinanceLedgerService $ledger,
        FundingLotAllocator $allocator,
    ): int {
        // 源表归档护栏（T+7 之后）：--execute --fresh 会先清空新表再从旧表复制，
        // 旧表改名归档后如果放行，新表数据会被清掉且复制失败——必须在任何写入前拒绝。
        if (! Schema::hasTable('account_transactions')) {
            $this->error('旧台账 account_transactions 已归档（T+7 归档迁移执行后）：finance:rebuild 已退役，新账本即唯一真源，请勿重建。');

            return self::FAILURE;
        }

        $verifyOnly = (bool) $this->option('verify-only');
        $execute = (bool) $this->option('execute');
        $chunk = max(50, (int) $this->option('chunk'));

        $snapshot = $this->snapshot();

        if ($verifyOnly) {
            return $this->runVerification($snapshot);
        }

        if (! $execute) {
            return $this->dryRun($snapshot);
        }

        if (! $this->option('fresh') && $this->newTablesHaveData()) {
            $this->error('新表已有数据；如需重建请加 --fresh（会清空新表，不动旧表）');

            return self::FAILURE;
        }

        // 资金历史行「只增不减」是本项目的硬红线。--fresh 会物理清空 finance_ledger_entries
        // 等台账表，属于唯一违例路径：必须在显式环境开关下才允许，防止生产误执行。
        if ($this->option('fresh') && ! $this->freshAllowed()) {
            $this->error('--fresh 会物理清空新账本（台账/批次/消费/退款），属于资金红线唯一违例路径。');
            $this->line('确认是演练或切换窗口时，请在 .env 设置 FINANCE_REBUILD_ALLOW_FRESH=true 后重试。');

            return self::FAILURE;
        }

        // 空源守卫：旧台账为 0 行时重建是"静默成功"的空转（闸门全过但什么都没迁），
        // 生产切换窗口若误连空库/错误环境，必须在此硬失败而不是给一张假绿表。
        if ((int) $snapshot['ledger_rows'] === 0 && ! $this->option('allow-empty')) {
            $this->error('旧台账 account_transactions 为 0 行：疑似连错环境或源数据异常。');
            $this->line('确认是全新空库演练时，可加 --allow-empty 继续。');

            return self::FAILURE;
        }

        $runNo = 'MR'.date('YmdHis').strtoupper(bin2hex(random_bytes(4)));

        try {
            DB::transaction(function () use ($runNo, $snapshot, $chunk, $ledger, $allocator): void {
                if ($this->option('fresh')) {
                    $this->truncateNewTables();
                }

                $this->phase($runNo, 'snapshot', FinanceMigrationRun::STATUS_PASSED, $snapshot);

                $accountMap = $this->copyAccounts($runNo);
                $this->copyLedger($runNo, $chunk, $accountMap);
                $this->replayLots($runNo, $allocator);
                $this->calibrate($runNo, $ledger, $allocator);
                $this->copyRefunds($runNo, $chunk);
                $this->syncHotStandbyAfterRebuild($runNo);

                $gates = $this->verifyGates($snapshot);
                $this->phase(
                    $runNo,
                    'verify',
                    $this->allPassed($gates) ? FinanceMigrationRun::STATUS_PASSED : FinanceMigrationRun::STATUS_FAILED,
                    $gates,
                );

                if (! $this->allPassed($gates)) {
                    $this->renderGates($gates);
                    throw new RuntimeException('闸门校验未通过，已回滚全部写入');
                }
            });
        } catch (Throwable $error) {
            // 事务已回滚，阶段记录一并消失；单独补一条失败记录用于事后排查。
            FinanceMigrationRun::query()->create([
                'run_no' => $runNo,
                'phase' => 'aborted',
                'status' => FinanceMigrationRun::STATUS_FAILED,
                'stats' => ['error' => $error::class],
                'message' => mb_substr($error->getMessage(), 0, 500),
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => date('Y-m-d H:i:s'),
            ]);

            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info("重建完成：{$runNo}");

        return $this->printGates($snapshot);
    }

    /**
     * --fresh 的环境级闸门：只认显式开关，避免生产误执行物理清空。
     */
    private function freshAllowed(): bool
    {
        return (bool) config('finance.rebuild.allow_fresh', false);
    }

    /**
     * @return array<string, int|string>
     */
    private function snapshot(): array
    {
        return [
            'ledger_rows' => DB::table('account_transactions')->count(),
            'cash_rows' => DB::table('account_transactions')->where('account_type', 'cash')->count(),
            'referral_rows' => DB::table('account_transactions')->where('account_type', '!=', 'cash')->count(),
            'account_rows' => DB::table('user_accounts')->count(),
            'refund_rows' => DB::table('refunds')->count(),
            'refund_balance_rows' => DB::table('refunds')->where('refund_method', '<>', 'manual_mark')->count(),
            'refund_mark_rows' => DB::table('refunds')->where('refund_method', 'manual_mark')->count(),
            'invoice_status_sum' => (int) DB::table('invoices')->sum('status'),
            'invoice_refund_sum' => (string) DB::table('invoices')->sum('refund_amount'),
            'taken_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, int|string>  $snapshot
     */
    private function dryRun(array $snapshot): int
    {
        $calibration = $this->calibrationPreview();

        $this->info('模式：dry-run（未写入任何数据）');
        $this->table(['项', '数量'], [
            ['台账总行数', $snapshot['ledger_rows']],
            ['现金台账行', $snapshot['cash_rows']],
            ['返利台账行（只复制，不建批次）', $snapshot['referral_rows']],
            ['账户行', $snapshot['account_rows']],
            ['退款行', $snapshot['refund_rows']],
            ['其中真实动账', $snapshot['refund_balance_rows']],
            ['其中历史纯标记', $snapshot['refund_mark_rows']],
        ]);

        $this->table(['校准项', '值'], [
            ['需要校准的用户数', $calibration['users']],
            ['校准金额合计（元）', $calibration['total']],
        ]);

        $this->line('执行 --execute --fresh 才会写入。');

        return self::SUCCESS;
    }

    /**
     * @return array{users: int, total: string}
     */
    private function calibrationPreview(): array
    {
        $rows = DB::table('account_transactions')
            ->selectRaw("user_id, SUM(CASE WHEN account_type = 'cash' THEN change_amount ELSE 0 END) AS ledger_sum")
            ->groupBy('user_id')
            ->get();

        $ledgerSums = [];
        foreach ($rows as $row) {
            $ledgerSums[(int) $row->user_id] = Money::toCents($row->ledger_sum);
        }

        $users = 0;
        $total = 0;
        foreach (DB::table('user_accounts')->get(['user_id', 'cash_balance']) as $account) {
            $userId = (int) $account->user_id;
            $balance = Money::toCents($account->cash_balance);
            $diff = $balance - ($ledgerSums[$userId] ?? 0);
            if ($diff !== 0) {
                $users++;
                $total += $diff;
            }
        }

        return ['users' => $users, 'total' => Money::formatCents($total)];
    }

    /**
     * @return array<int, int> user_id => account_id
     */
    private function copyAccounts(string $runNo): array
    {
        $started = microtime(true);
        $now = date('Y-m-d H:i:s');

        $balances = [];
        foreach (DB::table('user_accounts')->get(['user_id', 'cash_balance']) as $row) {
            $balances[(int) $row->user_id] = Money::toCents($row->cash_balance);
        }

        // 有台账但无账户行的用户，按台账合计建户，避免遗漏。
        $ledgerUsers = DB::table('account_transactions')
            ->where('account_type', 'cash')
            ->selectRaw('user_id, SUM(change_amount) AS total')
            ->groupBy('user_id')
            ->get();
        foreach ($ledgerUsers as $row) {
            $userId = (int) $row->user_id;
            if (! array_key_exists($userId, $balances)) {
                $balances[$userId] = Money::toCents($row->total);
            }
        }

        $insert = [];
        foreach ($balances as $userId => $cents) {
            $insert[] = [
                'user_id' => $userId,
                'currency' => 'CNY',
                'available_amount' => Money::formatCents($cents),
                'frozen_amount' => '0.00',
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($insert, 500) as $chunk) {
            DB::table('finance_accounts')->insert($chunk);
        }

        $map = DB::table('finance_accounts')->pluck('id', 'user_id')->all();
        $map = array_map(static fn ($id): int => (int) $id, $map);

        $this->phase($runNo, 'accounts', FinanceMigrationRun::STATUS_PASSED, [
            'accounts' => count($map),
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);

        return $map;
    }

    /**
     * @param  array<int, int>  $accountMap
     */
    private function copyLedger(string $runNo, int $chunk, array $accountMap): void
    {
        $started = microtime(true);
        $copied = 0;

        DB::table('account_transactions')->orderBy('id')->chunkById($chunk, function ($rows) use ($accountMap, &$copied): void {
            $insert = [];
            foreach ($rows as $row) {
                $userId = (int) $row->user_id;
                $accountId = $accountMap[$userId] ?? null;
                if ($accountId === null && (string) ($row->account_type ?: 'cash') === 'cash') {
                    throw new RuntimeException("台账行 {$row->id} 的用户 {$userId} 没有对应现金账户");
                }

                $insert[] = [
                    'id' => (int) $row->id,
                    'entry_no' => 'LG'.$row->id,
                    'account_id' => $accountId,
                    'user_id' => $userId,
                    'account_type' => (string) ($row->account_type ?: 'cash'),
                    'event_type' => (string) $row->event_type,
                    'change_amount' => (string) $row->change_amount,
                    'balance_after' => (string) $row->balance_after,
                    'currency' => (string) ($row->currency ?: 'CNY'),
                    'funding_lot_id' => null,
                    'source_type' => $row->source_type,
                    'source_id' => $row->source_id,
                    'origin_type' => $row->origin_type,
                    'origin_id' => $row->origin_id,
                    'operator_type' => $row->operator ? 'legacy' : null,
                    'operator_id' => null,
                    'operator_name' => $row->operator,
                    'remark' => $row->remark,
                    'trace_id' => $row->trace_id,
                    'created_at' => $row->created_at,
                ];
                $copied++;
            }

            DB::table('finance_ledger_entries')->insert($insert);
        });

        $this->phase($runNo, 'ledger', FinanceMigrationRun::STATUS_PASSED, [
            'copied' => $copied,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
    }

    private function replayLots(string $runNo, FundingLotAllocator $allocator): void
    {
        $started = microtime(true);
        $lots = 0;
        $consumptions = 0;
        $shortfalls = 0;

        $userIds = DB::table('finance_ledger_entries')
            ->where('account_type', 'cash')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $account = FinanceAccount::query()->where('user_id', (int) $userId)->where('currency', 'CNY')->first();
            if (! $account instanceof FinanceAccount) {
                continue;
            }

            $entries = FinanceLedgerEntry::query()
                ->where('user_id', (int) $userId)
                ->where('account_type', 'cash')
                ->orderBy('id')
                ->get();

            foreach ($entries as $entry) {
                $cents = Money::toCents($entry->change_amount);
                if ($cents > 0) {
                    $allocator->createLot(
                        $account,
                        $entry,
                        $cents,
                        'replayed',
                        $entry->source_type,
                        $entry->source_id !== null ? (int) $entry->source_id : null,
                        $entry->origin_type,
                        $entry->origin_id !== null ? (int) $entry->origin_id : null,
                        $this->entryTime($entry),
                    );
                    $lots++;

                    continue;
                }

                if ($cents < 0) {
                    $result = $allocator->allocate(
                        $account,
                        -$cents,
                        (int) $entry->id,
                        [
                            'source_type' => $entry->source_type,
                            'source_id' => $entry->source_id !== null ? (int) $entry->source_id : null,
                        ],
                        $this->entryTime($entry),
                        false,
                    );
                    $consumptions += count($result['allocations']);
                    if ($result['shortfall_cents'] > 0) {
                        // 历史支出找不到来源：建一个负值缺口批次并消费掉，
                        // 使「批次剩余合计」与「台账合计」同时对齐真实余额；
                        // 缺口批次带独立 data_quality，便于人工识别，不冒充真实资金。
                        $shortfallLot = $allocator->createLot(
                            $account,
                            $entry,
                            -$result['shortfall_cents'],
                            'calibration_shortfall',
                            'migration',
                            null,
                            null,
                            null,
                            $this->entryTime($entry),
                        );
                        FinanceConsumption::query()->create([
                            'account_id' => (int) $account->id,
                            'user_id' => (int) $account->user_id,
                            'lot_id' => (int) $shortfallLot->id,
                            'entry_id' => (int) $entry->id,
                            'source_type' => $entry->source_type,
                            'source_id' => $entry->source_id,
                            'amount' => Money::formatCents($result['shortfall_cents']),
                            'restored_amount' => '0.00',
                            'refunded_amount' => '0.00',
                            'created_at' => $this->entryTime($entry),
                        ]);

                        $shortfalls++;
                        FinanceReconciliationDiff::query()->updateOrCreate(
                            [
                                'check_type' => 'unallocated_debit',
                                'scope_type' => 'ledger_entry',
                                'scope_id' => (int) $entry->id,
                            ],
                            [
                                'expected_value' => Money::formatCents(-$cents),
                                'actual_value' => Money::formatCents(-$cents - $result['shortfall_cents']),
                                'diff_value' => Money::formatCents(-$result['shortfall_cents']),
                                'status' => FinanceReconciliationDiff::STATUS_OPEN,
                                'detected_at' => date('Y-m-d H:i:s'),
                            ],
                        );
                    }
                }
            }
        }

        $this->phase($runNo, 'lots', FinanceMigrationRun::STATUS_PASSED, [
            'lots' => $lots,
            'consumptions' => $consumptions,
            'shortfall_entries' => $shortfalls,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
    }

    private function calibrate(string $runNo, FinanceLedgerService $ledger, FundingLotAllocator $allocator): void
    {
        $started = microtime(true);
        $users = 0;
        $total = 0;

        $accounts = FinanceAccount::query()->where('currency', 'CNY')->get();

        foreach ($accounts as $account) {
            $ledgerSum = Money::toCents(
                FinanceLedgerEntry::query()
                    ->where('user_id', (int) $account->user_id)
                    ->where('account_type', 'cash')
                    ->sum('change_amount')
            );
            $balance = Money::toCents($account->available_amount);
            $diff = $balance - $ledgerSum;

            if ($diff === 0) {
                continue;
            }

            $entry = $ledger->append($account, 'calibration', $diff, $balance, [
                'source_type' => 'migration',
                'operator_type' => 'system',
                'operator_name' => 'finance:rebuild',
                'remark' => '存量校准：以账户余额为准补记差额，不改动历史行',
            ]);

            $allocator->createLot(
                $account,
                $entry,
                $diff,
                'calibration',
                'migration',
                null,
                null,
                null,
                date('Y-m-d H:i:s'),
            );

            $users++;
            $total += $diff;
        }

        $this->phase($runNo, 'calibration', FinanceMigrationRun::STATUS_PASSED, [
            'users' => $users,
            'total_amount' => Money::formatCents($total),
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
    }

    /**
     * 重建后把热备列对齐到新账户表（含补建缺失的 user_accounts 行）。
     *
     * 必须在事务内、闸门之前执行：校准/复制直写新表不会经过内核的 syncHotStandby，
     * 漏掉这步会让 user_accounts.cash_balance 停留在旧值——
     * 代码回滚场景下旧内核将读到未校准的余额（资金口径错误）。
     */
    private function syncHotStandbyAfterRebuild(string $runNo): void
    {
        if (! (bool) config('finance.hot_standby', true)) {
            $this->phase($runNo, 'hot_standby_sync', FinanceMigrationRun::STATUS_SKIPPED, ['reason' => 'hot_standby=false']);

            return;
        }

        $now = date('Y-m-d H:i:s');

        // 补建缺失行（列集与内核 syncHotStandby 的 insertOrIgnore 保持一致）
        DB::statement(<<<SQL
            INSERT IGNORE INTO user_accounts
                (user_id, cash_balance, credit_limit, referral_frozen_balance,
                 referral_available_balance, referral_pending_withdrawal_balance,
                 referral_withdrawn_balance, version, created_at, updated_at)
            SELECT fa.user_id, fa.available_amount, 0, 0, 0, 0, 0, 0, '{$now}', '{$now}'
            FROM finance_accounts fa
            LEFT JOIN user_accounts ua ON ua.user_id = fa.user_id
            WHERE ua.user_id IS NULL
        SQL);

        DB::statement(
            'UPDATE user_accounts ua JOIN finance_accounts fa ON fa.user_id = ua.user_id '
            ."SET ua.cash_balance = fa.available_amount, ua.updated_at = '{$now}'"
        );

        $this->phase($runNo, 'hot_standby_sync', FinanceMigrationRun::STATUS_PASSED, [
            'users' => DB::table('finance_accounts')->count(),
        ]);
    }

    private function copyRefunds(string $runNo, int $chunk): void
    {
        $started = microtime(true);
        $copied = 0;

        DB::table('refunds')->orderBy('id')->chunkById($chunk, function ($rows) use (&$copied): void {
            $insert = [];
            foreach ($rows as $row) {
                $isMark = (string) ($row->refund_method ?? '') === 'manual_mark';
                $insert[] = [
                    'id' => (int) $row->id,
                    'refund_no' => (string) $row->refund_no,
                    'user_id' => (int) $row->user_id,
                    'invoice_id' => (int) $row->invoice_id,
                    'refund_invoice_id' => $row->refund_invoice_id,
                    'payment_id' => $row->payment_id,
                    'amount' => (string) $row->amount,
                    'currency' => (string) ($row->currency ?: 'CNY'),
                    'status' => (int) $row->status,
                    'funds_moved' => $isMark ? 0 : 1,
                    'data_origin' => $isMark ? 'legacy_manual_mark' : 'legacy_balance',
                    'reason' => (string) ($row->reason ?? ''),
                    'delivery_evidence' => null,
                    'operator_type' => $row->operator_type ?: 'legacy',
                    'operator_id' => $row->operator_id,
                    'operator_name' => $row->operator_name,
                    'trace_id' => $row->trace_id,
                    'refunded_at' => $row->refunded_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
                $copied++;
            }

            DB::table('finance_refunds')->insert($insert);
        });

        $this->phase($runNo, 'refunds', FinanceMigrationRun::STATUS_PASSED, [
            'copied' => $copied,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
    }

    /**
     * @param  array<string, int|string>  $snapshot
     * @return array<string, array{passed: bool, detail: string}>
     */
    private function verifyGates(array $snapshot): array
    {
        return [
            'G1_rows' => $this->gate(
                (int) $snapshot['ledger_rows'] === DB::table('finance_ledger_entries')->where('entry_no', 'like', 'LG%')->count()
                && (int) $snapshot['refund_rows'] === DB::table('finance_refunds')->count(),
                '历史台账/退款行数与旧表一致（校准行为新增，不计入）',
            ),
            'G2_balance' => $this->gateBalance(),
            'G3_lots' => $this->gateLots(),
            'G4_consumptions' => $this->gateConsumptions(),
            'G5_refund_amount' => $this->gate(
                Money::toCents(DB::table('finance_refunds')->where('funds_moved', 1)->sum('amount'))
                === Money::toCents(DB::table('refunds')->where('refund_method', '<>', 'manual_mark')->sum('amount')),
                '真实动账退款金额一致',
            ),
            'G6_invoices' => $this->gate(
                (int) DB::table('invoices')->sum('status') === (int) $snapshot['invoice_status_sum']
                && Money::toCents(DB::table('invoices')->sum('refund_amount')) === Money::toCents($snapshot['invoice_refund_sum']),
                '账单状态与退款额未被改动',
            ),
            'G7_foreign_refs' => $this->gateForeignRefs(),
        ];
    }

    /**
     * @param  array<string, int|string>  $snapshot
     */
    private function runVerification(array $snapshot): int
    {
        $gates = $this->verifyGates($snapshot);
        $this->renderGates($gates);

        return $this->allPassed($gates) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, array{passed: bool, detail: string}>  $gates
     */
    private function renderGates(array $gates): void
    {
        $this->table(
            ['闸门', '结果', '说明'],
            array_map(
                static fn (string $key, array $result): array => [$key, $result['passed'] ? 'PASS' : 'FAIL', $result['detail']],
                array_keys($gates),
                array_values($gates),
            )
        );
    }

    /**
     * @param  array<string, int|string>  $snapshot
     */
    private function printGates(array $snapshot): int
    {
        return $this->runVerification($snapshot);
    }

    /**
     * @return array{passed: bool, detail: string}
     */
    private function gate(bool $passed, string $detail): array
    {
        return ['passed' => $passed, 'detail' => $passed ? $detail : $detail.'（不一致）'];
    }

    /**
     * @return array{passed: bool, detail: string}
     */
    private function gateBalance(): array
    {
        $mismatch = DB::table('finance_accounts as a')
            ->whereRaw(
                "CAST(a.available_amount * 100 AS SIGNED) <> (
                    SELECT COALESCE(CAST(SUM(e.change_amount) * 100 AS SIGNED), 0)
                    FROM finance_ledger_entries e
                    WHERE e.user_id = a.user_id AND e.account_type = 'cash'
                )"
            )
            ->count();

        return $this->gate($mismatch === 0, "按用户台账合计与余额一致（不一致 {$mismatch} 户）");
    }

    /**
     * @return array{passed: bool, detail: string}
     */
    private function gateLots(): array
    {
        $mismatch = DB::table('finance_accounts as a')
            ->whereRaw(
                'CAST(a.available_amount * 100 AS SIGNED) <> (
                    SELECT COALESCE(CAST(SUM(l.remaining_amount) * 100 AS SIGNED), 0)
                    FROM finance_funding_lots l
                    WHERE l.account_id = a.id
                )'
            )
            ->count();

        return $this->gate($mismatch === 0, "批次剩余合计与账户余额一致（不一致 {$mismatch} 户）");
    }

    /**
     * @return array{passed: bool, detail: string}
     */
    private function gateConsumptions(): array
    {
        $overflow = DB::table('finance_ledger_entries as e')
            ->where('e.account_type', 'cash')
            ->where('e.change_amount', '<', 0)
            ->whereRaw(
                'COALESCE((
                    SELECT SUM(c.amount) * 100
                    FROM finance_consumptions c
                    WHERE c.entry_id = e.id
                ), 0) > ABS(CAST(e.change_amount * 100 AS SIGNED))'
            )
            ->count();

        return $this->gate($overflow === 0, "消费映射不超过出账金额（越界 {$overflow} 笔）");
    }

    /**
     * @return array{passed: bool, detail: string}
     */
    private function gateForeignRefs(): array
    {
        $ledgerMissing = DB::table('recharge_records')
            ->whereNotNull('account_transaction_id')
            ->whereRaw('NOT EXISTS (SELECT 1 FROM finance_ledger_entries e WHERE e.id = recharge_records.account_transaction_id)')
            ->count();
        $refundMissing = DB::table('recharge_records')
            ->whereNotNull('refund_id')
            ->whereRaw('NOT EXISTS (SELECT 1 FROM finance_refunds r WHERE r.id = recharge_records.refund_id)')
            ->count();

        return $this->gate(
            $ledgerMissing === 0 && $refundMissing === 0,
            "充值凭证外键引用可解析（台账缺失 {$ledgerMissing}，退款缺失 {$refundMissing}）",
        );
    }

    /**
     * @param  array<string, array{passed: bool, detail: string}>  $gates
     */
    private function allPassed(array $gates): bool
    {
        foreach ($gates as $gate) {
            if (! $gate['passed']) {
                return false;
            }
        }

        return true;
    }

    private function newTablesHaveData(): bool
    {
        return FinanceAccount::query()->exists()
            || FinanceLedgerEntry::query()->exists()
            || FinanceFundingLot::query()->exists()
            || DB::table('finance_refunds')->exists();
    }

    private function entryTime(FinanceLedgerEntry $entry): string
    {
        $value = $entry->getAttribute('created_at');

        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d H:i:s')
            : (string) ($value ?? date('Y-m-d H:i:s'));
    }

    private function truncateNewTables(): void
    {
        DB::table('finance_refund_allocations')->delete();
        DB::table('finance_consumptions')->delete();
        DB::table('finance_funding_lots')->delete();
        DB::table('finance_ledger_entries')->delete();
        DB::table('finance_refunds')->delete();
        DB::table('finance_accounts')->delete();
        FinanceOperation::query()->where('scope', 'like', 'rebuild%')->delete();
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function phase(string $runNo, string $phase, string $status, array $stats): void
    {
        FinanceMigrationRun::query()->create([
            'run_no' => $runNo,
            'phase' => $phase,
            'status' => $status,
            'stats' => $stats,
            'message' => self::PHASE_LABELS[$phase] ?? $phase,
            'started_at' => date('Y-m-d H:i:s'),
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
