<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Support\Upgrade\BaselineInspector;
use App\Support\Upgrade\DatabaseBackupRunner;
use App\Support\Upgrade\MigrationInventory;
use App\Support\Upgrade\UpgradeLedger;
use App\Support\Upgrade\UpgradeLock;
use App\Support\Upgrade\VersionManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * 受控数据库升级编排。
 *
 * 职责边界：本类只负责编排顺序（加锁 → 版本校验 → 备份 → 委托 migrate → 记账），
 * 迁移本身交给 Laravel 内置 migrator，不重写执行器（见方案 D1）。
 *
 * 失败语义（见方案 D5）：
 * - 本次没有任何迁移成功执行 → failed，可修代码后原地重试；
 * - 本次至少有一个迁移成功 → recovery_required，结构已部分前进，必须人工核对，
 *   绝不自动重跑，因为 MySQL DDL 无法随事务回滚。
 */
class DatabaseUpgradeService
{
    public function __construct(
        private readonly UpgradeLedger $ledger,
        private readonly DatabaseBackupRunner $backups,
        private readonly UpgradeLock $lock,
        private readonly BaselineInspector $baseline,
    ) {}

    /**
     * 只读盘点当前升级状态。
     *
     * @return array{
     *     code_version: string,
     *     database_version: ?string,
     *     adopted: bool,
     *     pending: list<string>,
     *     allowed_missing: list<string>,
     *     unexpected_missing: list<string>,
     *     baseline: array{available: bool, baseline_tables: int, baseline_migrations: int, live_tables: int, live_migrations: int, drifted: bool},
     *     latest_run: ?array<string, mixed>,
     *     requires_attention: bool
     * }
     *
     * @throws RuntimeException 版本清单缺失或迁移目录不可读时抛出
     */
    public function status(VersionManifest $manifest): array
    {
        $inventory = MigrationInventory::build();
        $baseline = $this->baseline->compare(
            base_path('database/schema/mysql-schema.sql'),
            count($this->tableNames()),
            count($inventory->applied),
        );

        return [
            'code_version' => $manifest->version,
            'database_version' => $this->ledger->currentVersion(),
            'adopted' => $this->ledger->isAdopted(),
            'pending' => $inventory->pending,
            'allowed_missing' => $inventory->allowedMissing,
            'unexpected_missing' => $inventory->unexpectedMissing,
            'baseline' => $baseline,
            'latest_run' => $this->ledger->latestRun(),
            'requires_attention' => $inventory->requiresAttention()
                || $this->ledger->currentVersion() !== $manifest->version
                || $baseline['drifted'],
        ];
    }

    /**
     * 当前实库的基础表清单，用于基线与实库比对。
     *
     * @return list<string>
     */
    private function tableNames(): array
    {
        return array_map(
            static fn (mixed $row): string => (string) (is_object($row) ? ($row->table_name ?? '') : $row),
            DB::select(
                'SELECT table_name AS table_name FROM information_schema.tables'
                .' WHERE table_schema = DATABASE() AND table_type = \'BASE TABLE\'',
            ),
        );
    }

    /**
     * 执行受控升级。
     *
     * @param  VersionManifest  $manifest  当前代码包版本清单
     * @param  bool  $dryRun  只打印将执行的 SQL，不写库不记账
     * @param  bool  $skipBackup  跳过备份（production 由命令层拒绝）
     * @param  string|null  $adoptVersion  首次采纳时指定的版本
     * @return array{status: string, executed: list<string>, backup: ?string, message: string}
     *
     * @throws RuntimeException 版本不兼容、清单异常或备份失败时抛出
     */
    public function upgrade(
        VersionManifest $manifest,
        bool $dryRun = false,
        bool $skipBackup = false,
        ?string $adoptVersion = null,
    ): array {
        return $this->lock->synchronized(function () use ($manifest, $dryRun, $skipBackup, $adoptVersion): array {
            return $this->runLocked($manifest, $dryRun, $skipBackup, $adoptVersion);
        });
    }

    /**
     * 在已持有升级锁的前提下执行完整流程。
     *
     * @return array{status: string, executed: list<string>, backup: ?string, message: string}
     *
     * @throws RuntimeException 校验失败或迁移失败时抛出
     */
    private function runLocked(
        VersionManifest $manifest,
        bool $dryRun,
        bool $skipBackup,
        ?string $adoptVersion,
    ): array {
        $inventory = MigrationInventory::build();
        $databaseVersion = $this->ledger->currentVersion();

        // 首次采纳：账本中还没有任何安装记录。
        if (! $this->ledger->isAdopted()) {
            if ($adoptVersion === null) {
                throw new RuntimeException(
                    '数据库尚未采纳到升级账本。新安装请执行 python backend/scripts/install_db.py；'
                    .'既有数据库首次接入请执行 php artisan db:upgrade --adopt='.$manifest->version,
                );
            }

            return $this->adopt($manifest, $adoptVersion, $inventory, $dryRun, $skipBackup);
        }

        // 已采纳的库再次传 --adopt 属于误用：静默忽略会让操作者以为重新采纳生效，
        // 实际执行的是普通升级。必须显式拒绝，避免掩盖真实版本推进。
        if ($adoptVersion !== null) {
            throw new RuntimeException(
                '数据库已采纳到升级账本（当前版本 '.(string) $databaseVersion.'），不能再使用 --adopt；'
                .'如需执行升级请直接运行 php artisan db:upgrade --force',
            );
        }

        $this->assertVersionTransition((string) $databaseVersion, $manifest);

        if ($inventory->unexpectedMissing !== []) {
            throw new RuntimeException(
                '数据库存在代码包中缺失且不在白名单内的迁移记录，已拒绝升级：'
                .implode(', ', $inventory->unexpectedMissing),
            );
        }

        if ($dryRun) {
            Artisan::call('migrate', ['--pretend' => true, '--force' => true]);

            return [
                'status' => 'dry-run',
                'executed' => [],
                'backup' => null,
                'message' => trim(Artisan::output()),
            ];
        }

        $backupPath = null;
        if (! $skipBackup) {
            $backupPath = $this->backups->create();
        }

        $runId = $this->ledger->begin(
            (string) $databaseVersion,
            $manifest->version,
            count($inventory->pending),
            $backupPath,
        );

        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                throw new RuntimeException('数据库迁移命令返回非零退出码：'.$exitCode."\n".$output);
            }
        } catch (Throwable $exception) {
            $this->recordFailure($runId, $exception, $inventory->pending);
            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException($exception->getMessage(), 0, $exception);
        }

        $this->ledger->complete($runId, $manifest->version, $inventory->pending);

        return [
            'status' => 'completed',
            'executed' => $inventory->pending,
            'backup' => $backupPath,
            'message' => '数据库迁移完成，版本已推进到 '.$manifest->version,
        ];
    }

    /**
     * 首次采纳既有数据库。
     *
     * 既有库没有升级账本，而账本表本身由一个迁移创建，因此采纳必须分两步：
     * 1. 先执行 pending 迁移建出 system_installation / system_upgrade_runs；
     * 2. 再写入版本与 adopted 记录。
     *
     * 只采纳不迁移是不够的——那会让「账本存在但表不存在」成为永久状态。
     * 注意：此路径同样执行迁移，因此同样需要备份，不能因名为 adopt 而绕过。
     *
     * @param  MigrationInventory  $inventory  当前迁移盘点
     * @param  bool  $dryRun  是否仅演练
     * @param  bool  $skipBackup  是否跳过备份
     * @return array{status: string, executed: list<string>, backup: ?string, message: string}
     *
     * @throws RuntimeException 采纳版本与代码版本不一致或迁移失败时抛出
     */
    private function adopt(
        VersionManifest $manifest,
        string $adoptVersion,
        MigrationInventory $inventory,
        bool $dryRun,
        bool $skipBackup,
    ): array {
        if ($adoptVersion !== $manifest->version) {
            throw new RuntimeException(
                "采纳版本 {$adoptVersion} 与代码包版本 {$manifest->version} 不一致；"
                .'首次采纳必须使用当前代码包版本，否则会掩盖真实的待执行迁移。',
            );
        }

        if ($dryRun) {
            return [
                'status' => 'dry-run',
                'executed' => [],
                'backup' => null,
                'message' => '将采纳数据库版本 '.$adoptVersion.'，并执行 '.count($inventory->pending).' 个待执行迁移',
            ];
        }

        $backupPath = $skipBackup ? null : $this->backups->create();

        // 账本表此刻还不存在，无法先写 run 记录；先迁移，成功后再补记。
        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new RuntimeException('首次采纳时执行迁移失败，请人工核对实库状态后重试：'."\n".$output);
        }

        $runId = $this->ledger->begin('', $adoptVersion, count($inventory->pending), $backupPath);
        $this->ledger->adopt($adoptVersion, $runId);

        return [
            'status' => 'adopted',
            'executed' => $inventory->pending,
            'backup' => $backupPath,
            'message' => '数据库已采纳到升级账本，版本记为 '.$adoptVersion,
        ];
    }

    /**
     * 校验来源版本是否允许升级到目标版本。
     *
     * @throws RuntimeException 降级或低于最低升级版本时抛出
     */
    private function assertVersionTransition(string $fromVersion, VersionManifest $manifest): void
    {
        if (version_compare($fromVersion, $manifest->version, '>')) {
            throw new RuntimeException(
                "数据库版本 {$fromVersion} 高于代码版本 {$manifest->version}，禁止执行降级迁移。",
            );
        }

        if (! $manifest->allowsUpgradeFrom($fromVersion)) {
            throw new RuntimeException(
                "数据库版本 {$fromVersion} 低于最低可升级版本 {$manifest->minimumUpgradeVersion}，请先升级到中间版本。",
            );
        }
    }

    /**
     * 记录失败并按「本次是否有迁移成功」区分严重度。
     *
     * @param  list<string>  $pendingBefore  本次开始前的待执行清单
     */
    private function recordFailure(int $runId, Throwable $exception, array $pendingBefore): void
    {
        $remaining = MigrationInventory::build()->pending;
        $executed = array_values(array_diff($pendingBefore, $remaining));

        $status = $executed === [] ? UpgradeLedger::STATUS_FAILED : UpgradeLedger::STATUS_RECOVERY_REQUIRED;

        try {
            $this->ledger->fail($runId, $status, $exception->getMessage(), $executed);
        } catch (Throwable) {
            // 账本写入失败不能覆盖原始迁移异常，原始异常优先向上抛出。
        }
    }
}
