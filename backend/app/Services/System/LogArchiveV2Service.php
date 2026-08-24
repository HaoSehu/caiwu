<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\ArchiveItem;
use Carbon\CarbonImmutable;
use Illuminate\Process\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * V2 归档协议实现：planned -> staging -> verified -> published -> purging -> purged。
 *
 * 与旧版 db:archive-logs 的差异：
 * - pt-archiver 只导出 .part，不带 --purge；
 * - 导出文件流式校验（CSV 表头、行数、ID 边界、SHA-256）通过后才原子发布为最终文件并写入 manifest；
 * - 源数据删除只允许发生在 published 之后，且必须显式 --purge；
 * - 与旧命令共用全局文件锁，避免两套协议并发操作同一批表。
 */
class LogArchiveV2Service
{
    private const PROTOCOL_VERSION = 'v2';

    public const COLD_SEARCH_MAX_ROWS = 50000;

    /** 冷检索单次最多读取的归档 CSV/manifest 估算字节数。 */
    public const COLD_SEARCH_MAX_BYTES = 128 * 1024 * 1024;

    /** 不让损坏/不可用批次的诊断摘要本身无限撑大响应。 */
    private const MAX_UNAVAILABLE_ARCHIVES = 500;

    /** manifest 只包含固定元数据；拒绝异常大的文件，避免校验时一次性读入内存。 */
    private const MAX_MANIFEST_BYTES = 4 * 1024 * 1024;

    /** 列表页单次最多做完整 manifest/CSV 可恢复性校验的文件数。 */
    private const MAX_LIST_RESTORE_CHECK_ITEMS = 20;

    /** 列表页单次最多读取/哈希的归档 CSV 总字节数。 */
    private const MAX_LIST_RESTORE_CHECK_BYTES = 32 * 1024 * 1024;

    /** @var array<string, array{signature: string, checksum: string|false}> */
    private array $fileChecksumCache = [];

    public function __construct(private readonly SettingService $settings) {}

    /**
     * 只读统计：每张白名单表的归档候选行数。不落库、不写文件。
     *
     * @param  list<string>  $tables
     * @return array<string, mixed>
     */
    public function overview(array $tables = [], ?int $retentionDays = null): array
    {
        $config = $this->resolveConfig();
        $retentionDays = $this->boundedInteger($retentionDays ?? $config['retention_days'], 1, 3650, 'retention days');
        $policies = $this->resolveTables($tables);
        $stats = [];

        foreach ($policies as $table => $description) {
            if (! Schema::hasTable($table)) {
                $stats[$table] = ['available' => false];

                continue;
            }
            $stats[$table] = [
                'available' => true,
                'description' => $description,
                'total' => (int) DB::table($table)->count(),
                'eligible' => (int) DB::table($table)
                    ->whereRaw('created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$retentionDays])
                    ->count(),
                'id_min' => DB::table($table)->whereRaw('created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$retentionDays])->min('id'),
                'id_max' => DB::table($table)->whereRaw('created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$retentionDays])->max('id'),
            ];
        }

        return [
            'mode' => 'overview',
            'retention_days' => $retentionDays,
            'tables' => $stats,
            'total_eligible' => array_sum(array_map(static fn (array $s): int => (int) ($s['eligible'] ?? 0), $stats)),
        ];
    }

    /**
     * 执行两阶段归档：规划落库 -> 暂存导出 -> 校验 -> 发布。不删除源数据。
     *
     * @param  list<string>  $tables
     * @return array<string, mixed>
     */
    public function archive(array $tables = [], ?int $retentionDays = null, ?string $batchId = null): array
    {
        $lock = $this->acquireGlobalLock();

        try {
            return $this->archiveLocked($tables, $retentionDays, $batchId);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * 执行归档并在同一把全局锁内完成清除，避免 archive 与 purge 之间产生
     * 第二个批次或被另一个归档进程插入。
     *
     * @param  list<string>  $tables
     * @return array<string, mixed>
     */
    public function archiveAndPurge(array $tables = [], ?int $retentionDays = null, ?string $batchId = null): array
    {
        $lock = $this->acquireGlobalLock();

        try {
            $archive = $this->archiveLocked($tables, $retentionDays, $batchId);
            if ((string) ($archive['status'] ?? '') === 'failed') {
                $archive['purge_skipped'] = 'archive 存在失败项，未执行源数据清除';

                return $archive;
            }

            $purge = $this->purgeLocked((string) ($archive['batch_id'] ?? ''));
            $purge['archive'] = [
                'batch_id' => $archive['batch_id'] ?? null,
                'status' => $archive['status'] ?? null,
            ];

            return $purge;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param list<string> $tables @return array<string, mixed> */
    private function archiveLocked(array $tables, ?int $retentionDays, ?string $batchId): array
    {
        $config = $this->resolveConfig();
        $explicitTables = $tables !== [];
        $policies = $this->resolveTables($tables);
        $requestedRetention = $retentionDays ?? $config['retention_days'];
        $requestedRetention = $this->boundedInteger($requestedRetention, 1, 3650, 'retention days');
        $batchId = $batchId !== null ? trim($batchId) : '';
        $items = collect();

        try {
            if ($batchId !== '') {
                $items = ArchiveItem::query()->where('batch_id', $batchId)->orderBy('id')->get();
                if ($items->isEmpty()) {
                    throw new InvalidArgumentException("Unknown archive batch: {$batchId}");
                }

                $existingTables = $items->pluck('table_name')->map(static fn (mixed $table): string => (string) $table)->sort()->values()->all();
                $requestedTables = array_keys($policies);
                sort($requestedTables);
                if ($explicitTables && $requestedTables !== $existingTables) {
                    throw new InvalidArgumentException('指定批次的表集合与 --table 不一致，拒绝复用。');
                }
                $policies = $this->resolveTables(
                    $items->pluck('table_name')->map(static fn (mixed $table): string => (string) $table)->all(),
                );
            } else {
                $batchId = Str::uuid()->toString();
                $this->assertPreconditions($config, $policies);
                $items = $this->planItems($batchId, $policies, $requestedRetention);
            }

            $needsExport = $items->contains(static fn (ArchiveItem $item): bool => in_array(
                $item->status,
                [ArchiveItem::STATUS_PLANNED, ArchiveItem::STATUS_STAGING, ArchiveItem::STATUS_VERIFIED, ArchiveItem::STATUS_FAILED],
                true,
            ));
            if ($needsExport) {
                $this->assertPreconditions($config, $policies);
            } else {
                $this->assertSchemaPreconditions($policies);
            }

            $pendingStages = [];
            foreach ($items as $item) {
                if ($item->status === ArchiveItem::STATUS_PUBLISHED) {
                    try {
                        $this->validatePublishedItem($item, $config, false, false);
                    } catch (Throwable $exception) {
                        $this->markNeedsRecovery($item, $exception->getMessage());
                    }
                }
                if (in_array($item->status, [
                    ArchiveItem::STATUS_PURGED,
                    ArchiveItem::STATUS_PUBLISHED,
                    ArchiveItem::STATUS_PURGING,
                    ArchiveItem::STATUS_NEEDS_RECOVERY,
                ], true)) {
                    continue;
                }

                if ($item->status === ArchiveItem::STATUS_FAILED) {
                    $this->resetFailedItemForRetry($item, $config);
                }

                $prepared = $this->prepareStageItem($item, $config);
                if ($prepared === null) {
                    continue;
                }

                if ($prepared['command'] === null) {
                    $this->verifyItem($item);
                    if ($item->status === ArchiveItem::STATUS_VERIFIED) {
                        $this->publishItem($item, $config);
                    }

                    continue;
                }

                $pendingStages[] = [$item, $prepared['command']];
            }

            $stageGroups = array_chunk($pendingStages, $config['concurrency']);
            $stageTimeout = max(60, intdiv(3300, max(1, count($stageGroups))));
            foreach ($stageGroups as $group) {
                if (count($group) === 1) {
                    [$item, $command] = $group[0];
                    $result = Process::timeout($stageTimeout)->run($command);
                    $this->finishStageProcess($item, $result);
                    if ($item->status !== ArchiveItem::STATUS_FAILED) {
                        $this->verifyItem($item);
                    }
                    if ($item->status === ArchiveItem::STATUS_VERIFIED) {
                        $this->publishItem($item, $config);
                    }

                    continue;
                }

                $results = Process::concurrently(function (Pool $pool) use ($group, $stageTimeout): void {
                    foreach ($group as [$item, $command]) {
                        $pool->as((string) $item->id)->timeout($stageTimeout)->command($command);
                    }
                });

                foreach ($group as [$item]) {
                    $result = $results[(string) $item->id];
                    $this->finishStageProcess($item, $result);
                    if ($item->status !== ArchiveItem::STATUS_FAILED) {
                        $this->verifyItem($item);
                    }
                    if ($item->status === ArchiveItem::STATUS_VERIFIED) {
                        $this->publishItem($item, $config);
                    }
                }
            }

            return $this->batchReport($batchId, $items);
        } catch (Throwable $exception) {
            // 已发布/已清除的归档物是可恢复证据，异常不能把它们改成 failed。
            // 规划阶段可能正是因为 archive_items 迁移缺失而抛错；此时再
            // 无条件查询同一张表会覆盖原始诊断异常，健康/CLI 只能看到
            // 二次 QueryException。表存在时才回写未发布状态。
            try {
                if (Schema::hasTable('archive_items')) {
                    DB::table('archive_items')
                        ->where('batch_id', $batchId)
                        ->whereIn('status', [
                            ArchiveItem::STATUS_PLANNED,
                            ArchiveItem::STATUS_STAGING,
                        ])
                        ->update([
                            'status' => ArchiveItem::STATUS_FAILED,
                            'error_message' => mb_substr($exception->getMessage(), 0, 500),
                            'updated_at' => now(),
                        ]);
                }
            } catch (Throwable) {
                // 状态回写是补偿动作；无论表/连接是否可用，都保留原始异常。
            }

            throw $exception;
        }
    }

    /**
     * 对已发布批次执行源数据分块删除。必须显式调用，且只允许操作 published 项。
     *
     * @return array<string, mixed>
     */
    public function purge(string $batchId): array
    {
        $lock = $this->acquireGlobalLock();

        try {
            return $this->purgeLocked($batchId);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, mixed> */
    private function purgeLocked(string $batchId): array
    {
        $config = $this->resolveConfig();
        $items = ArchiveItem::query()->where('batch_id', $batchId)->orderBy('id')->get();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException("Unknown archive batch: {$batchId}");
        }

        $policies = $this->resolveTables(
            $items->pluck('table_name')->map(static fn (mixed $table): string => (string) $table)->all(),
        );
        $this->assertSchemaPreconditions($policies);

        // 整批预检必须全部通过后才允许第一条 DELETE，避免半批次破坏恢复边界。
        foreach ($items as $item) {
            if ($item->status === ArchiveItem::STATUS_PURGED) {
                continue;
            }
            if (! in_array($item->status, [ArchiveItem::STATUS_PUBLISHED, ArchiveItem::STATUS_PURGING], true)) {
                throw new RuntimeException("Archive item {$item->table_name} is not published or resumable (status={$item->status}); refusing to purge.");
            }
            try {
                $this->validatePublishedItem($item, $config, true, true);
            } catch (Throwable $exception) {
                $this->recordPurgeFailure($item, $exception);

                throw $exception;
            }
        }

        foreach ($items as $item) {
            if ($item->status === ArchiveItem::STATUS_PURGED) {
                continue;
            }
            try {
                $this->purgeItem($item);
            } catch (Throwable $exception) {
                $this->recordPurgeFailure($item, $exception);

                throw $exception;
            }
        }

        $cleanup = $this->cleanupLocked($config);

        return [
            'batch_id' => $batchId,
            'mode' => 'purge',
            'status' => $cleanup['errors'] === [] ? 'completed' : 'failed',
            'items' => $items->map(fn (ArchiveItem $item): array => $this->itemSummary($item))->all(),
            'cleanup' => $cleanup,
        ];
    }

    /**
     * 只读恢复校验：检查 manifest、最终文件、大小和 SHA-256 是否一致。不导入数据。
     *
     * @return array<string, mixed>
     */
    public function restoreDryRun(string $batchId): array
    {
        $items = ArchiveItem::query()->where('batch_id', $batchId)->get();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException("Unknown archive batch: {$batchId}");
        }
        $this->resolveTables(
            $items->pluck('table_name')->map(static fn (mixed $table): string => (string) $table)->all(),
        );

        $results = [];

        foreach ($items as $item) {
            $valid = $this->isRestorable($item);

            $results[] = [
                'table' => $item->table_name,
                'status' => $item->status,
                'restorable' => $valid,
                'published_path' => $item->published_path,
                'file_size' => $item->file_size,
                'checksum_sha256' => $item->checksum_sha256,
                'reason' => $valid ? null : 'manifest 或归档物缺失/不匹配，不能作为恢复依据',
            ];
        }

        $restorableCount = count(array_filter($results, static fn (array $r): bool => (bool) $r['restorable']));

        return [
            'batch_id' => $batchId,
            'mode' => 'restore_dry_run',
            'status' => $restorableCount === count($results) ? 'completed' : 'failed',
            'items' => $results,
            'restorable_count' => $restorableCount,
        ];
    }

    /**
     * 只读列出归档批次物（冷检索打底），带可恢复性校验。
     *
     * @param  array{table?: string, status?: string, batch_id?: string, limit?: int}  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        $query = ArchiveItem::query();

        if (! empty($filters['table'])) {
            $table = trim((string) $filters['table']);
            $this->resolveTables([$table]);
            $query->where('table_name', $table);
        }
        if (! empty($filters['status'])) {
            $status = trim((string) $filters['status']);
            if (! in_array($status, [
                ArchiveItem::STATUS_PLANNED,
                ArchiveItem::STATUS_STAGING,
                ArchiveItem::STATUS_VERIFIED,
                ArchiveItem::STATUS_PUBLISHED,
                ArchiveItem::STATUS_PURGING,
                ArchiveItem::STATUS_PURGED,
                ArchiveItem::STATUS_FAILED,
                ArchiveItem::STATUS_NEEDS_RECOVERY,
            ], true)) {
                throw new InvalidArgumentException("Unsupported archive status: {$status}");
            }
            $query->where('status', $status);
        }
        if (! empty($filters['batch_id'])) {
            $query->where('batch_id', trim((string) $filters['batch_id']));
        }

        $limit = max(1, min((int) ($filters['limit'] ?? 100), 500));
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : null;
        $pageSize = isset($filters['page_size']) ? max(1, min((int) $filters['page_size'], 100)) : null;
        $total = (int) (clone $query)->count();
        $itemsQuery = $query->orderByDesc('id');
        if ($page !== null && $pageSize !== null) {
            $offset = $page > intdiv(PHP_INT_MAX, $pageSize)
                ? PHP_INT_MAX
                : ($page - 1) * $pageSize;
            $itemsQuery->skip($offset)->take($pageSize);
        } else {
            $itemsQuery->limit($limit);
        }

        $restoreBudget = [
            'items' => 0,
            'bytes' => 0,
        ];
        $items = $itemsQuery->get()->map(function (ArchiveItem $item) use (&$restoreBudget): array {
            $restorable = $this->listRestorable($item, $restoreBudget);

            return array_merge($this->itemSummary($item), [
                'restorable' => $restorable['value'],
                'restorable_check' => $restorable['check'],
                'restorable_reason' => $restorable['reason'],
                'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
            ]);
        })->all();

        return [
            'mode' => 'list',
            'count' => count($items),
            'total' => $page !== null && $pageSize !== null ? $total : min($total, $limit),
            'page' => $page ?? 1,
            'page_size' => $pageSize ?? min($limit, count($items)),
            'items' => $items,
        ];
    }

    /**
     * 与旧版 db:archive-logs 对齐的阶段前置校验：
     * 凭据文件必须可读、pt-archiver 必须可执行，否则在产生任何暂存物前失败。
     *
     * @param  array<string, mixed>  $config
     */
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $policies
     */
    private function assertPreconditions(array $config, array $policies): void
    {
        $this->assertSchemaPreconditions($policies);
        $archiveRoot = (string) ($config['archive_root'] ?? '');
        if ($archiveRoot === '' || ! $this->isPathWithin($archiveRoot, storage_path())) {
            throw new RuntimeException('V2 archive root must remain inside backend storage.');
        }

        $defaultsFile = (string) $config['defaults_file'];
        if ($defaultsFile === '' || ! is_file($defaultsFile) || ! is_readable($defaultsFile)) {
            throw new RuntimeException("pt-archiver defaults file is not readable: {$defaultsFile}");
        }
        if (str_contains($defaultsFile, ',')) {
            throw new RuntimeException('pt-archiver defaults file path cannot contain commas.');
        }

        $binary = (string) $config['binary'];
        $version = Process::timeout(10)->run([$binary, '--version']);
        if ($version->failed()) {
            $message = trim($version->errorOutput() ?: $version->output());
            throw new RuntimeException('pt-archiver is unavailable'.($message !== '' ? ": {$message}" : '.'));
        }
    }

    /** @param array<string, string> $policies */
    private function assertSchemaPreconditions(array $policies): void
    {
        $database = trim((string) DB::getDatabaseName());
        if ($database === '' || preg_match('/^[A-Za-z0-9_$-]+$/', $database) !== 1) {
            throw new RuntimeException('Database name contains unsupported characters; refusing to archive or purge.');
        }

        foreach (array_keys($policies) as $table) {
            if (preg_match('/^[A-Za-z0-9_]+$/', (string) $table) !== 1) {
                throw new RuntimeException("Unsupported log table name: {$table}");
            }
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required log table does not exist: {$table}");
            }
            foreach (['id', 'created_at'] as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Required log table {$table} is missing column {$column}; refusing to archive or purge.");
                }
            }

            // SQLite/测试驱动没有 information_schema；生产 MySQL 才强制 InnoDB，
            // 防止非事务引擎在分块清除时留下不可恢复的半批次。
            $driver = (string) DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                $engine = DB::table('information_schema.tables')
                    ->where('table_schema', $database)
                    ->where('table_name', $table)
                    ->value('engine');
                if (strtoupper((string) $engine) !== 'INNODB') {
                    throw new RuntimeException("Log table {$table} must use InnoDB before V2 archive/purge.");
                }
            }
        }
    }

    /**
     * @param  array<string, string>  $policies
     * @return Collection<int, ArchiveItem>
     */
    private function planItems(string $batchId, array $policies, int $retentionDays): Collection
    {
        $items = collect();
        $cutoff = CarbonImmutable::now()->subDays($retentionDays);

        foreach (array_keys($policies) as $table) {
            $eligible = DB::table($table)
                ->where('created_at', '<', $cutoff);

            // 一个已规划/已发布的批次已经“认领”了自己的 ID 范围。下一次
            // 无 batch-id 的归档不能再次导出同一批源记录，否则第一次 purge
            // 后第二个批次只能落入 needs_recovery，并且会产生重复归档物。
            // 归档项只记录范围而不记录每个 ID，因此同时按原批次 cutoff 约束：
            // 新一轮保留期变长时，范围内此前尚未符合旧 cutoff 的较新行仍可入选。
            // 任意元数据损坏（缺 cutoff）都按 fail-closed 处理，整段范围暂不重导，
            // 由显式 --batch-id 重试或人工恢复。
            $claimedItems = ArchiveItem::query()
                ->where('table_name', $table)
                ->whereNotNull('id_min')
                ->whereNotNull('id_max')
                ->get(['id_min', 'id_max', 'cutoff_at']);
            foreach ($claimedItems as $claimed) {
                $minId = (int) $claimed->id_min;
                $maxId = (int) $claimed->id_max;
                if ($minId < 1 || $maxId < $minId) {
                    continue;
                }

                $claimedCutoff = $claimed->cutoff_at?->toDateTimeString();
                $eligible->where(function ($query) use ($minId, $maxId, $claimedCutoff): void {
                    $query->whereNotBetween('id', [$minId, $maxId]);
                    if ($claimedCutoff !== null && $claimedCutoff !== '') {
                        $query->orWhere('created_at', '>=', $claimedCutoff);
                    }
                });
            }

            $expectedRows = (int) (clone $eligible)->count();

            if ($expectedRows === 0) {
                $items->push(ArchiveItem::query()->create([
                    'batch_id' => $batchId,
                    'table_name' => $table,
                    'status' => ArchiveItem::STATUS_PURGED,
                    'cutoff_at' => $cutoff,
                    'expected_rows' => 0,
                    'exported_rows' => 0,
                    'deleted_rows' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                continue;
            }

            $items->push(ArchiveItem::query()->create([
                'batch_id' => $batchId,
                'table_name' => $table,
                'status' => ArchiveItem::STATUS_PLANNED,
                'cutoff_at' => $cutoff,
                'id_min' => (int) (clone $eligible)->min('id'),
                'id_max' => (int) (clone $eligible)->max('id'),
                'expected_rows' => $expectedRows,
                'exported_rows' => 0,
                'deleted_rows' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{command: list<string>|null}|null
     */
    private function prepareStageItem(ArchiveItem $item, array $config): ?array
    {
        $partPath = $this->partPath($item, $config);
        $this->ensureDirectory(dirname($partPath));
        $wasStaging = $item->status === ArchiveItem::STATUS_STAGING;
        $storedPartPath = trim((string) ($item->part_path ?? ''));

        // 阶段元数据来自数据库，不能把其中被篡改/损坏的路径直接交给
        // rename()/fopen()。staging/verified 只能复用当前批次按受控根目录
        // 计算出的 .part；路径不一致时停在失败状态并要求人工恢复。
        if (($wasStaging || $item->status === ArchiveItem::STATUS_VERIFIED)
            && $storedPartPath !== ''
            && $this->normalizePathKey($storedPartPath) !== $this->normalizePathKey($partPath)) {
            $this->failItem($item, 'staging metadata path does not match the controlled archive path; refusing to reuse it.');

            return null;
        }

        if ($item->status === ArchiveItem::STATUS_VERIFIED) {
            // verify 落库后进程可能在 publish 前退出；已校验的 .part 必须直接
            // 进入发布阶段，不能删除证据并重新从源表导出。
            if (! is_file($partPath)) {
                $this->failItem($item, 'verified staging file is missing; refusing to re-export.');

                return null;
            }

            return ['command' => null];
        }

        if ($wasStaging && is_file($partPath)) {
            // 中断恢复：同一批次同一表已有 .part 时复用，避免重复导出追加。
            $item->forceFill([
                'status' => ArchiveItem::STATUS_STAGING,
                'part_path' => $partPath,
                'started_at' => now(),
                'updated_at' => now(),
            ])->save();

            return ['command' => null];
        }

        // planned/failed 状态下的旧 .part 不能因为“看起来完整”而被发布；
        // 重新执行必须从干净文件开始。
        if (is_file($partPath) && ! @unlink($partPath)) {
            $this->failItem($item, 'Unable to remove stale staging file before retry.');

            return null;
        }

        $item->forceFill([
            'status' => ArchiveItem::STATUS_STAGING,
            'part_path' => $partPath,
            'started_at' => now(),
            'updated_at' => now(),
        ])->save();

        return ['command' => [
            (string) $config['binary'],
            '--source=F='.(string) $config['defaults_file'].',D='.DB::getDatabaseName().',t='.$item->table_name.',i=PRIMARY',
            '--where=created_at < \''.(string) ($item->cutoff_at?->format('Y-m-d H:i:s') ?? '').'\'',
            '--file='.$partPath,
            '--output-format=csv',
            '--header',
            '--limit='.(string) $config['batch_size'],
            '--commit-each',
            '--sleep='.(string) $config['sleep_seconds'],
            '--retries=3',
            '--statistics',
            '--why-quit',
            '--no-version-check',
        ]];
    }

    private function finishStageProcess(ArchiveItem $item, mixed $result): void
    {
        if ($result->failed()) {
            $item->forceFill([
                'status' => ArchiveItem::STATUS_FAILED,
                'error_message' => mb_substr(trim($result->errorOutput() ?: $result->output()), 0, 500),
                'updated_at' => now(),
            ])->save();
        }
    }

    /** @param array<string, mixed> $config */
    private function resetFailedItemForRetry(ArchiveItem $item, array $config): void
    {
        if ($item->published_path !== null || $item->manifest_path !== null) {
            throw new RuntimeException("Archive item {$item->table_name} has published metadata but is marked failed; manual recovery is required.");
        }

        $partPath = trim((string) ($item->part_path ?? ''));
        if ($partPath !== '' && is_file($partPath)) {
            $archiveRoot = (string) ($config['archive_root'] ?? '');
            if (! $this->isPathWithin($partPath, $archiveRoot) || ! @unlink($partPath)) {
                throw new RuntimeException('Failed archive staging path is outside the controlled archive root or cannot be removed; manual recovery is required.');
            }
        }

        $item->forceFill([
            'status' => ArchiveItem::STATUS_PLANNED,
            'part_path' => null,
            'error_message' => null,
            'started_at' => null,
            'updated_at' => now(),
        ])->save();
    }

    private function verifyItem(ArchiveItem $item): void
    {
        $partPath = (string) ($item->part_path ?? '');
        if (! is_file($partPath)) {
            $this->failItem($item, 'staging file not found after export');

            return;
        }

        try {
            $inspection = $this->inspectCsv($partPath, $item);
            $rows = $inspection['rows'];
            $minId = $inspection['min_id'];
            $maxId = $inspection['max_id'];
            $size = $inspection['size'];
            $checksum = $this->fileChecksum($partPath);
            if ($checksum === false) {
                throw new RuntimeException('Unable to calculate staged archive checksum.');
            }

            if ($rows !== $item->expected_rows) {
                $this->failItem($item, "exported rows ({$rows}) do not match expected ({$item->expected_rows}); refusing to publish.");

                return;
            }

            if ($minId < $item->id_min || $maxId > $item->id_max) {
                $this->failItem($item, "exported id range [{$minId},{$maxId}] exceeds planned [{$item->id_min},{$item->id_max}].");

                return;
            }

            // 发布前也核对精确 ID 序列；仅比较行数和边界无法发现 [1,3]
            // 替代 [1,2] 的篡改，不能把这种文件交给后续 purge。
            $this->assertSourceMatchesArchive($item, $inspection, 0, $partPath);

            $item->forceFill([
                'status' => ArchiveItem::STATUS_VERIFIED,
                'exported_rows' => $rows,
                'file_size' => $size,
                'checksum_sha256' => $checksum,
                'error_message' => null,
                'verified_at' => now(),
                'updated_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $this->failItem($item, mb_substr($exception->getMessage(), 0, 500));
        }
    }

    /**
     * 流式检查 CSV：表头含 id，所有数据行 ID 落在 [id_min,id_max] 内，行数一致。
     *
     * @return array{rows: int, min_id: int, max_id: int, size: int, id_sequence_sha256: string}
     */
    private function inspectCsv(string $path, ArchiveItem $item): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open staged archive file.');
        }

        $rows = 0;
        $minId = PHP_INT_MAX;
        $maxId = 0;
        $previousId = null;
        $sequence = hash_init('sha256');
        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);
            throw new RuntimeException('Staged CSV is missing the id header.');
        }

        $columns = array_map(static fn (mixed $column): string => ltrim((string) $column, "\xEF\xBB\xBF"), $header);
        $idIndex = array_search('id', $columns, true);
        $createdAtIndex = array_search('created_at', $columns, true);
        $columnCount = count($columns);
        if ($idIndex === false || $createdAtIndex === false) {
            fclose($handle);
            throw new RuntimeException('Staged CSV is missing the id or created_at header.');
        }
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || (count($row) === 1 && trim((string) ($row[0] ?? '')) === '')) {
                continue;
            }
            if (count($row) !== $columnCount) {
                fclose($handle);
                throw new RuntimeException('Staged CSV contains a row with an unexpected number of columns.');
            }
            $rawId = trim((string) ($row[$idIndex] ?? ''));
            if ($rawId === '' || filter_var($rawId, FILTER_VALIDATE_INT) === false) {
                fclose($handle);
                throw new RuntimeException('Staged CSV contains a non-integer id.');
            }
            if (! array_key_exists($createdAtIndex, $row) || trim((string) $row[$createdAtIndex]) === '') {
                fclose($handle);
                throw new RuntimeException('Staged CSV contains a row without created_at.');
            }
            try {
                CarbonImmutable::parse(trim((string) $row[$createdAtIndex]));
            } catch (Throwable) {
                fclose($handle);
                throw new RuntimeException('Staged CSV contains an invalid created_at.');
            }
            $id = (int) $rawId;
            if ($id <= 0 || ($previousId !== null && $id <= $previousId)) {
                fclose($handle);
                throw new RuntimeException('Staged CSV IDs must be positive and strictly increasing.');
            }
            $rows++;
            $minId = min($minId, $id);
            $maxId = max($maxId, $id);
            $previousId = $id;
            hash_update($sequence, $id."\n");
        }

        fclose($handle);

        if ($rows === 0) {
            throw new RuntimeException('Staged CSV contains no data rows despite expected rows > 0.');
        }

        return [
            'rows' => $rows,
            'min_id' => $minId,
            'max_id' => $maxId,
            'size' => max(0, (int) filesize($path)),
            'id_sequence_sha256' => hash_final($sequence),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function publishItem(ArchiveItem $item, array $config): void
    {
        $partPath = (string) ($item->part_path ?? '');
        $publishedPath = $this->publishedPath($item, $config);
        $manifestPath = $this->manifestPath($item, $config);
        $this->ensureDirectory(dirname($publishedPath));

        if (! is_file($publishedPath)) {
            if ($partPath === '' || ! is_file($partPath) || ! rename($partPath, $publishedPath)) {
                $this->failItem($item, 'Unable to atomically publish staged archive file.');

                return;
            }
        } elseif ($partPath !== '' && is_file($partPath)) {
            if ((string) $this->fileChecksum($publishedPath) !== (string) $item->checksum_sha256) {
                $this->failItem($item, 'Existing published archive file checksum differs from the verified staging file.');

                return;
            }
            @unlink($partPath);
        }

        $manifest = [
            'protocol' => self::PROTOCOL_VERSION,
            'batch_id' => $item->batch_id,
            'table' => $item->table_name,
            'cutoff_at' => $item->cutoff_at?->toDateTimeString(),
            'id_min' => $item->id_min,
            'id_max' => $item->id_max,
            'expected_rows' => $item->expected_rows,
            'exported_rows' => $item->exported_rows,
            'file' => basename($publishedPath),
            'file_size' => $item->file_size,
            'checksum_sha256' => $item->checksum_sha256,
            'id_sequence_sha256' => $this->inspectCsv($publishedPath, $item)['id_sequence_sha256'],
            'published_at' => now()->toISOString(),
        ];

        $temporaryManifest = $manifestPath.'.part';
        $encodedManifest = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
        $handle = fopen($temporaryManifest, 'wb');
        if ($handle === false || fwrite($handle, $encodedManifest) !== strlen($encodedManifest) || ! fflush($handle)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($temporaryManifest);
            $this->failItem($item, 'Unable to write manifest after publish; source rows must NOT be deleted without manifest.');

            return;
        }
        if (function_exists('fsync')) {
            @fsync($handle);
        }
        fclose($handle);
        if (! rename($temporaryManifest, $manifestPath)) {
            @unlink($temporaryManifest);
            $this->failItem($item, 'Unable to atomically publish archive manifest; source rows must NOT be deleted without manifest.');

            return;
        }
        @chmod($manifestPath, 0640);
        @chmod($publishedPath, 0640);

        $item->forceFill([
            'status' => ArchiveItem::STATUS_PUBLISHED,
            'published_path' => $publishedPath,
            'manifest_path' => $manifestPath,
            'part_path' => null,
            'error_message' => null,
            'published_at' => now(),
            'updated_at' => now(),
        ])->save();
    }

    private function purgeItem(ArchiveItem $item): void
    {
        $item->forceFill([
            'status' => ArchiveItem::STATUS_PURGING,
            'updated_at' => now(),
        ])->save();

        $offset = max(0, (int) $item->deleted_rows);
        $chunk = [];

        foreach ($this->archiveIdChunks((string) $item->published_path, $offset, 500) as $ids) {
            $chunk = $ids;
            if ($chunk === []) {
                continue;
            }

            DB::transaction(function () use ($item, $chunk, $offset): void {
                $cutoff = $item->cutoff_at?->toDateTimeString();
                $matching = DB::table($item->table_name)
                    ->whereIn('id', $chunk)
                    ->where('created_at', '<', (string) $cutoff)
                    ->count();
                if ((int) $matching !== count($chunk)) {
                    throw new RuntimeException("Source rows for {$item->table_name} changed during purge; refusing partial delete.");
                }

                $deleted = DB::table($item->table_name)
                    ->whereIn('id', $chunk)
                    ->where('created_at', '<', (string) $cutoff)
                    ->delete();
                if ((int) $deleted !== count($chunk)) {
                    throw new RuntimeException("Unexpected delete count for {$item->table_name}: expected ".count($chunk).", got {$deleted}.");
                }

                DB::table('archive_items')->where('id', $item->id)->update([
                    'status' => ArchiveItem::STATUS_PURGING,
                    'deleted_rows' => $offset + count($chunk),
                    'updated_at' => now(),
                ]);
            });

            $offset += count($chunk);
            $item->deleted_rows = $offset;
        }

        if ($offset !== (int) $item->expected_rows) {
            throw new RuntimeException("Purge count for {$item->table_name} ({$offset}) does not match expected rows ({$item->expected_rows}).");
        }

        // 只确认本 manifest 固定 ID 边界已清空；边界外的新候选留给下一批归档。
        $remaining = (int) DB::table($item->table_name)
            ->whereBetween('id', [(int) $item->id_min, (int) $item->id_max])
            ->where('created_at', '<', (string) ($item->cutoff_at?->toDateTimeString() ?? ''))
            ->count();
        if ($remaining !== 0) {
            throw new RuntimeException("Source table {$item->table_name} still contains {$remaining} eligible rows after purge.");
        }

        $item->forceFill([
            'status' => ArchiveItem::STATUS_PURGED,
            'deleted_rows' => $offset,
            'purged_at' => now(),
            'updated_at' => now(),
        ])->save();
    }

    /**
     * 检查归档物的路径、manifest、文件大小、哈希、协议和边界。
     * strict=true 时还要求 CSV ID 序列与数据库当前剩余后缀完全相等。
     *
     * @param  array<string, mixed>  $config
     * @return array{rows: int, min_id: int, max_id: int, size: int, id_sequence_sha256: string}
     */
    private function validatePublishedItem(ArchiveItem $item, array $config, bool $strict, bool $checkSource): array
    {
        $publishedPath = (string) ($item->published_path ?? '');
        $manifestPath = (string) ($item->manifest_path ?? '');
        $root = (string) ($config['archive_root'] ?? '');
        if (trim((string) $item->batch_id) === ''
            || trim((string) $item->table_name) === ''
            || $item->cutoff_at === null
            || $item->id_min === null
            || $item->id_max === null
            || $item->expected_rows === null
            || $item->exported_rows === null
            || $item->file_size === null
            || ! preg_match('/^[a-f0-9]{64}$/i', (string) ($item->checksum_sha256 ?? ''))) {
            throw new RuntimeException("Archive item {$item->table_name} has incomplete archive metadata.");
        }
        if ($publishedPath === '' || $manifestPath === '' || ! $this->isPathWithin($publishedPath, $root) || ! $this->isPathWithin($manifestPath, $root)) {
            throw new RuntimeException("Archive item {$item->table_name} has an unsafe or missing archive path.");
        }
        if (! is_file($publishedPath) || ! is_file($manifestPath)) {
            throw new RuntimeException("Archive item {$item->table_name} manifest or CSV is missing.");
        }

        $manifestSize = @filesize($manifestPath);
        if ($manifestSize === false || $manifestSize > self::MAX_MANIFEST_BYTES) {
            throw new RuntimeException("Archive item {$item->table_name} manifest is too large or unreadable.");
        }

        $rawManifest = file_get_contents($manifestPath, false, null, 0, self::MAX_MANIFEST_BYTES + 1);
        if (is_string($rawManifest) && strlen($rawManifest) > self::MAX_MANIFEST_BYTES) {
            throw new RuntimeException("Archive item {$item->table_name} manifest exceeds the safety limit.");
        }
        $manifest = is_string($rawManifest) ? json_decode($rawManifest, true) : null;
        if (! is_array($manifest)) {
            throw new RuntimeException("Archive item {$item->table_name} has an invalid manifest.");
        }

        $expectedFile = basename($publishedPath);
        $cutoff = $item->cutoff_at?->toDateTimeString();
        $checks = [
            'protocol' => [(string) ($manifest['protocol'] ?? ''), self::PROTOCOL_VERSION],
            'batch_id' => [(string) ($manifest['batch_id'] ?? ''), (string) $item->batch_id],
            'table' => [(string) ($manifest['table'] ?? ''), (string) $item->table_name],
            'file' => [(string) ($manifest['file'] ?? ''), $expectedFile],
            'cutoff_at' => [$this->normalizeManifestCutoff($manifest['cutoff_at'] ?? null), (string) $cutoff],
            'id_min' => [(int) ($manifest['id_min'] ?? 0), (int) $item->id_min],
            'id_max' => [(int) ($manifest['id_max'] ?? 0), (int) $item->id_max],
            'expected_rows' => [(int) ($manifest['expected_rows'] ?? -1), (int) $item->expected_rows],
            'exported_rows' => [(int) ($manifest['exported_rows'] ?? -1), (int) $item->exported_rows],
            'file_size' => [(int) ($manifest['file_size'] ?? -1), (int) $item->file_size],
            'checksum_sha256' => [(string) ($manifest['checksum_sha256'] ?? ''), (string) $item->checksum_sha256],
        ];
        foreach ($checks as $name => [$actual, $expected]) {
            if ($actual !== $expected) {
                throw new RuntimeException("Archive item {$item->table_name} manifest {$name} mismatch.");
            }
        }

        $size = max(0, (int) filesize($publishedPath));
        if ($size !== (int) $item->file_size || $size !== (int) ($manifest['file_size'] ?? -1)) {
            throw new RuntimeException("Archive item {$item->table_name} file size mismatch.");
        }
        $checksum = $this->fileChecksum($publishedPath);
        if ($checksum === false || $checksum !== (string) $item->checksum_sha256) {
            throw new RuntimeException("Archive item {$item->table_name} checksum mismatch.");
        }

        $inspection = $this->inspectCsv($publishedPath, $item);
        if ($inspection['rows'] !== (int) $item->expected_rows || $inspection['min_id'] !== (int) $item->id_min || $inspection['max_id'] !== (int) $item->id_max) {
            throw new RuntimeException("Archive item {$item->table_name} CSV rows or ID bounds mismatch.");
        }
        $manifestSequence = (string) ($manifest['id_sequence_sha256'] ?? '');
        if (! preg_match('/^[a-f0-9]{64}$/i', $manifestSequence)
            || ! hash_equals(strtolower($manifestSequence), strtolower($inspection['id_sequence_sha256']))) {
            throw new RuntimeException("Archive item {$item->table_name} ID sequence checksum mismatch.");
        }

        if ($strict && $checkSource) {
            $offset = $item->status === ArchiveItem::STATUS_PURGING ? (int) $item->deleted_rows : 0;
            $this->assertSourceMatchesArchive($item, $inspection, $offset);
        }

        return $inspection;
    }

    private function normalizeManifestCutoff(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return CarbonImmutable::parse((string) $value)->toDateTimeString();
        } catch (Throwable) {
            return (string) $value;
        }
    }

    /** @param array{rows: int, min_id: int, max_id: int, size: int, id_sequence_sha256: string} $inspection */
    private function assertSourceMatchesArchive(ArchiveItem $item, array $inspection, int $offset, ?string $archivePath = null): void
    {
        if ($offset < 0 || $offset > $inspection['rows']) {
            throw new RuntimeException("Archive item {$item->table_name} deleted_rows is outside the exported sequence.");
        }

        // 归档批次的边界由 manifest 的 ID 范围固定；规划后新增的候选行属于下一批，
        // 不能让本批次在删除完成后因整表计数变化而被误判为 needs_recovery。
        $source = DB::table($item->table_name)
            ->whereBetween('id', [(int) $item->id_min, (int) $item->id_max])
            ->where('created_at', '<', (string) ($item->cutoff_at?->toDateTimeString() ?? ''))
            ->orderBy('id')
            ->cursor();
        $sourceIterator = $source->getIterator();
        $sourceIterator->rewind();
        $sourceIndex = 0;
        $sourceCount = 0;
        foreach ($this->archiveIds((string) ($archivePath ?? $item->published_path), $offset) as $archiveId) {
            if (! $sourceIterator->valid()) {
                throw new RuntimeException("Source ID sequence for {$item->table_name} is missing exported row {$archiveId}.");
            }
            $sourceRow = $sourceIterator->current();
            $sourceId = (int) ($sourceRow->id ?? 0);
            if ($sourceId !== $archiveId) {
                throw new RuntimeException("Source ID sequence for {$item->table_name} does not match manifest at position {$sourceIndex}.");
            }
            $sourceCount++;
            $sourceIndex++;
            $sourceIterator->next();
        }
        if ($sourceIterator->valid()) {
            throw new RuntimeException("Source table {$item->table_name} contains IDs outside the published archive sequence.");
        }
        if ($sourceCount !== (int) $item->expected_rows - $offset) {
            throw new RuntimeException("Source row count for {$item->table_name} does not match the remaining archive sequence.");
        }
        $eligibleCount = (int) DB::table($item->table_name)
            ->whereBetween('id', [(int) $item->id_min, (int) $item->id_max])
            ->where('created_at', '<', (string) ($item->cutoff_at?->toDateTimeString() ?? ''))
            ->count();
        if ($eligibleCount !== (int) $item->expected_rows - $offset) {
            throw new RuntimeException("Source eligible row count for {$item->table_name} changed since planning.");
        }
    }

    /** @return \Generator<int, int, void, void> */
    private function archiveIds(string $path, int $skip = 0): \Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open archive CSV for purge.');
        }
        try {
            $header = fgetcsv($handle);
            if (! is_array($header)) {
                throw new RuntimeException('Archive CSV is missing its header.');
            }
            $columns = array_map(static fn (mixed $column): string => ltrim((string) $column, "\xEF\xBB\xBF"), $header);
            $idIndex = array_search('id', $columns, true);
            if ($idIndex === false) {
                throw new RuntimeException('Archive CSV is missing the id header.');
            }
            $columnCount = count($columns);
            $index = 0;
            $previousId = null;
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || (count($row) === 1 && trim((string) ($row[0] ?? '')) === '')) {
                    continue;
                }
                if (count($row) !== $columnCount) {
                    throw new RuntimeException('Archive CSV contains a row with an unexpected number of columns.');
                }
                $rawId = trim((string) ($row[$idIndex] ?? ''));
                $validatedId = filter_var($rawId, FILTER_VALIDATE_INT);
                if ($rawId === '' || $validatedId === false || $validatedId <= 0) {
                    throw new RuntimeException('Archive CSV contains a non-positive integer id.');
                }
                $id = (int) $validatedId;
                if ($previousId !== null && $id <= $previousId) {
                    throw new RuntimeException('Archive CSV IDs must be positive and strictly increasing.');
                }
                $previousId = $id;
                if ($index++ < $skip) {
                    continue;
                }
                yield $id;
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return \Generator<int, list<int>, void, void> */
    private function archiveIdChunks(string $path, int $skip, int $chunkSize): \Generator
    {
        $chunk = [];
        foreach ($this->archiveIds($path, $skip) as $id) {
            $chunk[] = $id;
            if (count($chunk) >= $chunkSize) {
                yield $chunk;
                $chunk = [];
            }
        }
        if ($chunk !== []) {
            yield $chunk;
        }
    }

    private function isPathWithin(string $path, string $root): bool
    {
        if ($path === '' || $root === '') {
            return false;
        }
        $resolvedRoot = realpath($root);
        $resolvedPath = realpath($path);
        if ($resolvedRoot !== false && $resolvedPath !== false) {
            $path = $resolvedPath;
            $root = $resolvedRoot;
        }
        $normalize = static function (string $value): string {
            $value = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $value);
            $prefix = str_starts_with($value, DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : '';
            $parts = [];
            foreach (explode(DIRECTORY_SEPARATOR, $value) as $part) {
                if ($part === '' || $part === '.') {
                    continue;
                }
                if ($part === '..') {
                    array_pop($parts);

                    continue;
                }
                $parts[] = $part;
            }

            return $prefix.implode(DIRECTORY_SEPARATOR, $parts);
        };
        $path = $normalize($path);
        $root = $normalize($root);
        $prefix = $root.DIRECTORY_SEPARATOR;

        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $root = strtolower($root);
            $prefix = strtolower($prefix);
        }

        return $path === $root || str_starts_with($path, $prefix);
    }

    /**
     * 只读冷检索：在已发布的归档 CSV 中按表与时间范围流式匹配记录。
     * 不暴露物理路径，仅返回批次、ID、时间和归档文件名；不导入、不删除。
     * 传入 page/perPage 时按匹配结果切片并返回 total，供管理端接口分页。
     *
     * @param  array{table: string, start_date: string, end_date: string, limit?: int}  $filters
     * @return array<string, mixed>
     */
    public function search(array $filters, ?int $page = null, ?int $perPage = null): array
    {
        $table = trim((string) ($filters['table'] ?? ''));
        if ($table === '') {
            throw new InvalidArgumentException('search 必须指定 --table。');
        }
        $this->resolveTables([$table]);

        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;
        if (trim((string) $start) === '' || trim((string) $end) === '') {
            throw new InvalidArgumentException('search 必须同时指定 start_date 和 end_date，以限制冷检索范围。');
        }
        $start = $this->parseDate((string) $start, 'start_date');
        $end = $this->parseDate((string) $end, 'end_date');
        if ($start > $end) {
            throw new InvalidArgumentException('start_date 不能晚于 end_date。');
        }
        $limit = max(1, min((int) ($filters['limit'] ?? 100), 500));

        // 归档批次可能按日累积到很多万条；使用按主键倒序的惰性游标，
        // 避免一次性把全部元数据载入 PHP 内存。
        $items = ArchiveItem::query()
            ->where('table_name', $table)
            // purging 仍然保留已发布的 CSV，清除源表的断点不应让冷检索
            // 静默漏掉这一批；needs_recovery 也必须显式出现在不可用来源中。
            ->where(function ($query): void {
                // 失败/暂存/已校验但尚未发布的批次没有 published_path，
                // 也必须出现在结果中并标记 unavailable，不能被误报成“无历史”。
                $query->where('expected_rows', '>', 0)
                    ->orWhereNotNull('published_path')
                    ->orWhere('status', ArchiveItem::STATUS_NEEDS_RECOVERY);
            })
            ->orderByDesc('id')
            ->lazyByIdDesc(100);
        $config = $this->resolveConfig();
        $scanBudget = (int) ($config['cold_search_max_rows'] ?? self::COLD_SEARCH_MAX_ROWS);
        $scanByteBudget = (int) ($config['cold_search_max_bytes'] ?? self::COLD_SEARCH_MAX_BYTES);

        $matches = [];
        $total = 0;
        $bounded = $page !== null && $perPage !== null;
        $pageNumber = $bounded ? max(1, (int) $page) : 1;
        $pageSize = $bounded ? max(1, min((int) $perPage, 500)) : $limit;
        $pageOffset = $bounded
            ? ($pageNumber > intdiv(PHP_INT_MAX, $pageSize)
                ? PHP_INT_MAX
                : ($pageNumber - 1) * $pageSize)
            : 0;
        $restorableCache = [];
        $unavailableArchives = [];
        $unavailableArchivesTruncated = 0;
        $seenIds = [];

        $recordUnavailable = function (ArchiveItem $item, string $reason) use (&$unavailableArchives, &$unavailableArchivesTruncated): void {
            if (count($unavailableArchives) < self::MAX_UNAVAILABLE_ARCHIVES) {
                $unavailableArchives[] = $this->unavailableArchiveSummary($item, $reason);

                return;
            }

            $unavailableArchivesTruncated++;
        };

        foreach ($items as $item) {
            if (! in_array($item->status, [
                ArchiveItem::STATUS_PUBLISHED,
                ArchiveItem::STATUS_PURGING,
                ArchiveItem::STATUS_PURGED,
            ], true)) {
                $recordUnavailable($item, '归档批次当前状态不可用于冷检索。');

                continue;
            }

            $expectedRows = (int) $item->expected_rows;
            if ($expectedRows < 1) {
                $recordUnavailable($item, '归档元数据行数无效。');

                continue;
            }
            if ($expectedRows > $scanBudget) {
                $recordUnavailable($item, '归档行数超过本次冷检索上限，已跳过以保护服务资源。');

                continue;
            }

            $path = (string) ($item->published_path ?? '');
            $manifestPath = (string) ($item->manifest_path ?? '');
            $root = (string) $config['archive_root'];

            // 单个批次的路径异常不能中止同表其它归档物的检索；同时不把
            // 受控根目录之外的真实路径回显给调用方。
            if (! $this->isPathWithin($path, $root) || ! $this->isPathWithin($manifestPath, $root)) {
                $recordUnavailable($item, '归档路径不在受控目录内。');

                continue;
            }
            if (! is_file($path) || ! is_file($manifestPath)) {
                $recordUnavailable($item, '归档 CSV 或 manifest 缺失。');

                continue;
            }

            // isRestorable() 会读取 manifest、计算 CSV 哈希并完整解析 CSV，
            // 随后的窗口扫描还会再次读取 CSV。仅按 expected_rows 限制无法
            // 防止少量超大行/超大 manifest 把管理端 worker 拖入长时间 I/O。
            // 在任何完整读取前按约三次 CSV + 一次 manifest 估算并消耗字节预算。
            $csvSize = @filesize($path);
            $manifestSize = @filesize($manifestPath);
            if ($csvSize === false || $manifestSize === false) {
                $recordUnavailable($item, '无法读取归档文件大小。');

                continue;
            }
            $estimatedBytes = $csvSize > intdiv(PHP_INT_MAX - $manifestSize, 3)
                ? PHP_INT_MAX
                : ($csvSize * 3) + $manifestSize;
            if ($estimatedBytes > $scanByteBudget) {
                $recordUnavailable($item, '归档文件大小超过本次冷检索字节上限，已跳过以保护服务资源。');

                continue;
            }

            // 在完整校验前消耗预算；即使后续发现 manifest 损坏，也不能让
            // 恶意元数据通过多份异常文件绕过单次扫描上限。
            $scanBudget -= $expectedRows;
            $scanByteBudget -= $estimatedBytes;

            $cacheKey = (string) $item->id;
            $restorableCache[$cacheKey] ??= $this->isRestorable($item);
            if (! $restorableCache[$cacheKey]) {
                $recordUnavailable($item, 'manifest 或归档 CSV 校验失败。');

                continue;
            }

            // 逐行读取但只暂存当前页命中，避免把整份 CSV 的匹配行载入内存。
            // 文件若在扫描中途损坏，则丢弃本批次暂存结果，不污染全局计数。
            $fileRows = [];
            $fileSeenIds = [];
            $fileTotal = 0;
            try {
                foreach ($this->scanCsvWindow($path, $start, $end) as $row) {
                    $dedupeKey = $table.'|'.(string) $row['id'];
                    if (isset($seenIds[$dedupeKey]) || isset($fileSeenIds[$dedupeKey])) {
                        continue;
                    }
                    $fileSeenIds[$dedupeKey] = true;

                    if ($total + $fileTotal >= $pageOffset && count($matches) + count($fileRows) < $pageSize) {
                        $fileRows[] = [
                            'batch_id' => $item->batch_id,
                            'table' => $table,
                            'id' => $row['id'],
                            'created_at' => $row['created_at'],
                            'file' => basename($path),
                            'restorable' => $restorableCache[$cacheKey],
                        ];
                    }
                    $fileTotal++;
                }
            } catch (Throwable) {
                $recordUnavailable($item, '归档 CSV 读取或结构校验失败。');

                continue;
            }

            foreach ($fileSeenIds as $dedupeKey => $_seen) {
                $seenIds[$dedupeKey] = true;
            }
            $matches = array_merge($matches, $fileRows);
            $total += $fileTotal;
        }

        return [
            'mode' => 'search',
            'table' => $table,
            'start_date' => $start,
            'end_date' => $end,
            'count' => count($matches),
            'items' => $matches,
            'total' => $total,
            'page' => $pageNumber,
            'page_size' => $pageSize,
            'incomplete' => $unavailableArchives !== [],
            'unavailable_archives' => $unavailableArchives,
            'unavailable_archives_truncated' => $unavailableArchivesTruncated,
        ];
    }

    /** @return array<string, mixed> */
    private function unavailableArchiveSummary(ArchiveItem $item, string $reason): array
    {
        return [
            'batch_id' => (string) $item->batch_id,
            'table' => (string) $item->table_name,
            'status' => (string) $item->status,
            'file' => basename((string) ($item->published_path ?? '')) ?: null,
            'id_min' => $item->id_min,
            'id_max' => $item->id_max,
            'expected_rows' => (int) $item->expected_rows,
            'restorable' => false,
            'reason' => $reason,
        ];
    }

    /**
     * 列表页的可恢复性校验必须有硬预算。完整校验会 hash 并逐行解析 CSV，
     * 不能因为 page_size=100 就同步读取 100 个任意大小的归档文件。
     *
     * @param  array{items: int, bytes: int}  $budget
     * @return array{value: bool, check: string, reason: string|null}
     */
    private function listRestorable(ArchiveItem $item, array &$budget): array
    {
        if (! in_array($item->status, [
            ArchiveItem::STATUS_PUBLISHED,
            ArchiveItem::STATUS_PURGING,
            ArchiveItem::STATUS_PURGED,
        ], true)) {
            return [
                'value' => false,
                'check' => 'unavailable',
                'reason' => '归档批次当前状态不可恢复。',
            ];
        }

        if ((int) $item->expected_rows === 0) {
            $valid = $this->isRestorable($item);

            return [
                'value' => $valid,
                'check' => $valid ? 'verified' : 'invalid',
                'reason' => $valid ? null : '空批次元数据不完整。',
            ];
        }

        $path = (string) ($item->published_path ?? '');
        $root = (string) config('log_archive.archive_root', '');
        if (! $this->isPathWithin($path, $root) || ! is_file($path)) {
            return [
                'value' => false,
                'check' => 'unavailable',
                'reason' => '归档 CSV 缺失或路径不安全。',
            ];
        }

        $size = @filesize($path);
        if ($size === false) {
            return [
                'value' => false,
                'check' => 'unavailable',
                'reason' => '无法读取归档 CSV 大小。',
            ];
        }

        if ($budget['items'] >= self::MAX_LIST_RESTORE_CHECK_ITEMS
            || $size > self::MAX_LIST_RESTORE_CHECK_BYTES
            || $budget['bytes'] > self::MAX_LIST_RESTORE_CHECK_BYTES - $size) {
            return [
                'value' => false,
                'check' => 'unchecked',
                'reason' => '本次列表校验预算已用尽，请使用 restore-dry-run 做完整校验。',
            ];
        }

        $budget['items']++;
        $budget['bytes'] += $size;
        $valid = $this->isRestorable($item);

        return [
            'value' => $valid,
            'check' => $valid ? 'verified' : 'invalid',
            'reason' => $valid ? null : 'manifest 或归档 CSV 校验失败。',
        ];
    }

    /**
     * 流式读取已发布 CSV，返回 ID 与 created_at 落在时间窗口内的行。
     *
     * @return \Generator<int, array{id: int, created_at: string}, void, void>
     */
    private function scanCsvWindow(string $path, ?string $start, ?string $end): \Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open archive CSV for search.');
        }
        try {
            $header = fgetcsv($handle);
            if (! is_array($header)) {
                throw new RuntimeException('Archive CSV is missing its header.');
            }

            $columns = array_map(static fn (mixed $column): string => ltrim((string) $column, "\xEF\xBB\xBF"), $header);
            $idIndex = array_search('id', $columns, true);
            $createdAtIndex = array_search('created_at', $columns, true);
            $columnCount = count($columns);
            if ($idIndex === false || $createdAtIndex === false) {
                throw new RuntimeException('Archive CSV is missing the id or created_at header.');
            }
            $previousId = null;

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || (count($row) === 1 && trim((string) ($row[0] ?? '')) === '')) {
                    continue;
                }
                if (count($row) !== $columnCount) {
                    throw new RuntimeException('Archive CSV contains a row with an unexpected number of columns.');
                }
                $rawId = trim((string) $row[$idIndex]);
                $id = (int) $rawId;
                $createdAt = trim((string) ($row[$createdAtIndex] ?? ''));
                if ($rawId === '' || filter_var($rawId, FILTER_VALIDATE_INT) === false || $id <= 0 || $createdAt === '') {
                    throw new RuntimeException('Archive CSV contains an invalid id or created_at.');
                }
                try {
                    CarbonImmutable::parse($createdAt);
                } catch (Throwable) {
                    throw new RuntimeException('Archive CSV contains an invalid created_at.');
                }

                if (isset($previousId) && $id <= $previousId) {
                    throw new RuntimeException('Archive CSV IDs must be positive and strictly increasing.');
                }
                $previousId = $id;

                if ($start !== null && $createdAt < $start) {
                    continue;
                }
                if ($end !== null && $createdAt > $end) {
                    continue;
                }

                yield ['id' => $id, 'created_at' => $createdAt];
            }
        } finally {
            fclose($handle);
        }
    }

    private function parseDate(string $value, string $label): string
    {
        try {
            $date = CarbonImmutable::parse($value);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1) {
                $date = $label === 'end_date' ? $date->endOfDay() : $date->startOfDay();
            }

            return $date->format('Y-m-d H:i:s');
        } catch (Throwable) {
            throw new InvalidArgumentException("{$label} 不是有效日期。");
        }
    }

    private function isRestorable(ArchiveItem $item): bool
    {
        if (! in_array($item->status, [ArchiveItem::STATUS_PUBLISHED, ArchiveItem::STATUS_PURGING, ArchiveItem::STATUS_PURGED], true)) {
            return false;
        }

        // 没有候选行的批次没有 CSV/manifest，这是正常的“无数据”证据，
        // 不能被恢复检查误报为损坏；若同时出现任何文件元数据则仍视为异常。
        if ((int) $item->expected_rows === 0) {
            return $item->status === ArchiveItem::STATUS_PURGED
                && $item->published_path === null
                && $item->manifest_path === null
                && $item->part_path === null
                && $item->checksum_sha256 === null
                && $item->file_size === null
                && $item->id_min === null
                && $item->id_max === null
                && (int) $item->exported_rows === 0
                && (int) $item->deleted_rows === 0;
        }

        try {
            // 与 purge 使用同一套严格 manifest/CSV 协议校验，但不触碰在线源表。
            $this->validatePublishedItem($item, [
                'archive_root' => (string) config('log_archive.archive_root', ''),
            ], true, false);

            return true;
        } catch (Throwable) {
            // 可恢复性查询是 fail-closed：任何读取/解析异常都只能标记不可用，
            // 不能让调用方把异常归档物当成“零命中”。
            return false;
        }
    }

    /**
     * 清理超过 file_retention_days 的 V2 文件。
     * 只有已完成 purge 且 manifest/CSV 完整匹配时才允许删除；未知文件和孤立
     * .part 和未被 archive_items 引用的 CSV/manifest 永远保留并报告，避免把未
     * 完成批次当成过期文件误删或让失败发布留下无证据文件。
     *
     * @param  array<string, mixed>  $config
     * @return array{deleted_files: int, deleted_bytes: int, errors: list<string>, orphan_parts: list<string>, orphan_files: list<string>}
     */
    private function cleanupLocked(array $config): array
    {
        $retention = $this->boundedInteger($config['file_retention_days'] ?? 180, 1, 3650, 'file retention days');
        $threshold = CarbonImmutable::now()->subDays($retention);
        $result = ['deleted_files' => 0, 'deleted_bytes' => 0, 'errors' => [], 'orphan_parts' => [], 'orphan_files' => []];
        $root = (string) ($config['archive_root'] ?? '');
        $referencedPaths = collect();

        if (! Schema::hasTable('archive_items')) {
            $result['errors'][] = 'archive_items 表不存在，拒绝清理 V2 归档文件。';

            return $result;
        }

        try {
            $archiveItems = ArchiveItem::query()->get(['status', 'published_path', 'manifest_path']);
            $referencedPaths = $archiveItems
                ->flatMap(static fn (ArchiveItem $item): array => [
                    (string) ($item->published_path ?? ''),
                    (string) ($item->manifest_path ?? ''),
                ])
                ->filter()
                ->map(fn (string $path): string => $this->normalizePathKey($path))
                ->flip();
            $protectedPaths = $archiveItems
                ->where('status', '!=', ArchiveItem::STATUS_PURGED)
                ->flatMap(static fn (ArchiveItem $item): array => [
                    (string) ($item->published_path ?? ''),
                    (string) ($item->manifest_path ?? ''),
                ])
                ->filter()
                ->map(fn (string $path): string => $this->normalizePathKey($path))
                ->flip();
            ArchiveItem::query()
                ->where('status', ArchiveItem::STATUS_PURGED)
                ->whereNotNull('purged_at')
                ->where('purged_at', '<', $threshold)
                ->orderBy('id')
                ->get()
                ->each(function (ArchiveItem $item) use (&$result, $config, $protectedPaths): void {
                    $paths = [(string) $item->published_path, (string) $item->manifest_path];
                    $existingPaths = array_values(array_filter($paths, static fn (string $path): bool => is_file($path)));
                    // 清理已经成功完成过的批次必须幂等；两份证据都不存在时视为已清理，
                    // 只剩一份时仍报告异常，避免静默吞掉半套归档物。
                    if ($existingPaths === []) {
                        return;
                    }
                    if (count($existingPaths) !== count($paths)) {
                        $result['errors'][] = "Expired V2 archive item {$item->table_name}/{$item->batch_id} has incomplete evidence files.";

                        return;
                    }
                    try {
                        // 不检查源表：purged 后源记录本来就不应再存在；只验证文件证据。
                        $this->validatePublishedItem($item, $config, false, false);
                    } catch (Throwable $exception) {
                        $result['errors'][] = "{$item->table_name}/{$item->batch_id}: ".mb_substr($exception->getMessage(), 0, 300);

                        return;
                    }

                    foreach ($paths as $path) {
                        $normalizedPath = $this->normalizePathKey($path);
                        if (isset($protectedPaths[$normalizedPath])) {
                            $result['errors'][] = "Expired V2 archive file is still referenced by a non-purged item: {$path}";

                            continue;
                        }
                        $size = is_file($path) ? max(0, (int) filesize($path)) : 0;
                        if (@unlink($path)) {
                            $result['deleted_files']++;
                            $result['deleted_bytes'] += $size;
                        } elseif (is_file($path)) {
                            $result['errors'][] = "Unable to delete expired V2 archive file: {$path}";
                        }
                    }
                });
        } catch (Throwable $exception) {
            $result['errors'][] = '读取 archive_items 归档引用失败：'.mb_substr($exception->getMessage(), 0, 300);

            return $result;
        }

        if ($root !== '' && is_dir($root)) {
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS),
                );
                foreach ($iterator as $file) {
                    if (! $file->isFile() || $file->isLink()) {
                        continue;
                    }
                    $filename = strtolower((string) $file->getFilename());
                    $path = $file->getPathname();
                    if (str_ends_with($filename, '.part')) {
                        $result['orphan_parts'][] = $path;
                    } elseif (str_ends_with($filename, '.csv') || str_ends_with($filename, '.manifest.json')) {
                        $normalizedPath = $this->normalizePathKey($path);
                        if (! isset($referencedPaths[$normalizedPath])) {
                            $result['orphan_files'][] = $path;
                        }
                    }
                }
            } catch (Throwable $exception) {
                $result['errors'][] = 'Unable to scan V2 archive root: '.mb_substr($exception->getMessage(), 0, 300);
            }
        }

        return $result;
    }

    private function normalizePathKey(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($normalized) : $normalized;
    }

    /** @return array<string, mixed> */
    public function cleanup(?int $fileRetentionDays = null): array
    {
        $lock = $this->acquireGlobalLock();
        try {
            $config = $this->resolveConfig();
            if ($fileRetentionDays !== null) {
                $config['file_retention_days'] = $fileRetentionDays;
            }

            $cleanup = $this->cleanupLocked($config);

            return [
                'mode' => 'cleanup',
                'status' => $cleanup['errors'] === [] ? 'completed' : 'failed',
                'cleanup' => $cleanup,
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function markNeedsRecovery(ArchiveItem $item, string $message): void
    {
        $item->forceFill([
            'status' => ArchiveItem::STATUS_NEEDS_RECOVERY,
            'error_message' => mb_substr($message, 0, 500),
            'updated_at' => now(),
        ])->save();
    }

    private function recordPurgeFailure(ArchiveItem $item, Throwable $exception): void
    {
        $message = mb_substr($exception->getMessage(), 0, 500);
        if ($this->isRetryablePurgeFailure($exception)) {
            // 锁等待、死锁和短暂连接故障不会破坏归档证据。保留
            // published/purging 状态，让下一次 --purge --batch-id 继续重试；
            // 只记录错误，不把可重试故障升级为 needs_recovery。
            $item->forceFill([
                'error_message' => $message,
                'updated_at' => now(),
            ])->save();

            return;
        }

        // manifest/CSV、源数据序列或删除计数等完整性错误必须停在
        // needs_recovery，防止盲目重试再次删除不确定的数据。
        $this->markNeedsRecovery($item, $message);
    }

    private function isRetryablePurgeFailure(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        $code = strtoupper((string) $exception->getCode());
        $transientCode = in_array($code, [
            '1205', // MySQL lock wait timeout
            '1213', // MySQL deadlock
            '40001', // serialization failure
            '40P01', // PostgreSQL deadlock
            '2006', // server has gone away
            '2013', // lost connection during query
            '2055', // lost connection during query (MariaDB)
            '2002', // connection refused / cannot connect to server
            '2003', // cannot connect to MySQL server
        ], true);
        $transientMessage = str_contains($message, 'deadlock')
            || str_contains($message, 'lock wait timeout')
            || str_contains($message, 'serialization failure')
            || str_contains($message, 'database is locked')
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'lost connection')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'could not connect')
            || str_contains($message, 'unable to connect')
            || str_contains($message, 'connection timed out');

        return $transientCode || $transientMessage;
    }

    private function failItem(ArchiveItem $item, string $message): void
    {
        $item->forceFill([
            'status' => ArchiveItem::STATUS_FAILED,
            'error_message' => mb_substr($message, 0, 500),
            'updated_at' => now(),
        ])->save();
    }

    private function fileChecksum(string $path): string|false
    {
        if (! is_file($path)) {
            return false;
        }

        clearstatcache(true, $path);
        $size = filesize($path);
        $mtime = filemtime($path);
        $inode = function_exists('fileinode') ? fileinode($path) : false;
        $signature = implode(':', [
            (string) ($size === false ? -1 : $size),
            (string) ($mtime === false ? -1 : $mtime),
            (string) ($inode === false ? -1 : $inode),
        ]);

        if (($this->fileChecksumCache[$path]['signature'] ?? null) === $signature) {
            return $this->fileChecksumCache[$path]['checksum'];
        }

        $checksum = hash_file('sha256', $path);
        $this->fileChecksumCache[$path] = [
            'signature' => $signature,
            'checksum' => $checksum,
        ];

        return $checksum;
    }

    /**
     * @param  Collection<int, ArchiveItem>  $items
     * @return array<string, mixed>
     */
    private function batchReport(string $batchId, Collection $items): array
    {
        $failed = $items->where('status', ArchiveItem::STATUS_FAILED);
        $needsRecovery = $items->where('status', ArchiveItem::STATUS_NEEDS_RECOVERY);
        $incomplete = $items->reject(static fn (ArchiveItem $item): bool => in_array(
            $item->status,
            [ArchiveItem::STATUS_PUBLISHED, ArchiveItem::STATUS_PURGED],
            true,
        ));
        $status = $failed->isNotEmpty() || $needsRecovery->isNotEmpty()
            ? 'failed'
            : ($incomplete->isEmpty() ? 'completed' : 'in_progress');

        return [
            'batch_id' => $batchId,
            'mode' => 'archive',
            'status' => $status,
            'items' => $items->map(fn (ArchiveItem $item): array => $this->itemSummary($item))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function itemSummary(ArchiveItem $item): array
    {
        return [
            'batch_id' => $item->batch_id,
            'table' => $item->table_name,
            'status' => $item->status,
            'cutoff_at' => $item->cutoff_at?->format('Y-m-d H:i:s'),
            'id_min' => $item->id_min,
            'id_max' => $item->id_max,
            'expected_rows' => $item->expected_rows,
            'exported_rows' => $item->exported_rows,
            'deleted_rows' => $item->deleted_rows,
            'published_path' => $item->published_path,
            'manifest_path' => $item->manifest_path,
            'checksum_sha256' => $item->checksum_sha256,
            'file_size' => $item->file_size,
            'purged_at' => $item->purged_at?->format('Y-m-d H:i:s'),
            'error_message' => $item->error_message,
        ];
    }

    /** @return array<string, mixed> */
    private function resolveConfig(): array
    {
        $runtime = $this->settings->getLogArchiveConfig();

        return [
            'retention_days' => $this->boundedInteger($runtime['retention_days'] ?? config('log_archive.retention_days', 30), 1, 3650, 'retention days'),
            'file_retention_days' => $this->boundedInteger($runtime['file_retention_days'] ?? config('log_archive.file_retention_days', 180), 1, 3650, 'file retention days'),
            'archive_root' => rtrim((string) config('log_archive.archive_root'), DIRECTORY_SEPARATOR.'/\\'),
            'binary' => trim((string) ($runtime['pt_archiver_binary'] ?? config('log_archive.pt_archiver_binary'))),
            'defaults_file' => trim((string) ($runtime['pt_archiver_defaults_file'] ?? config('log_archive.pt_archiver_defaults_file'))),
            'batch_size' => $this->boundedInteger($runtime['batch_size'] ?? config('log_archive.batch_size', 1000), 100, 10000, 'batch size'),
            'sleep_seconds' => $this->boundedInteger($runtime['sleep_seconds'] ?? config('log_archive.sleep_seconds', 1), 0, 60, 'sleep seconds'),
            'concurrency' => $this->boundedInteger($runtime['concurrency'] ?? config('log_archive.concurrency', 2), 1, 8, 'concurrency'),
            'cold_search_max_rows' => $this->boundedInteger(
                config('log_archive.cold_search_max_rows', self::COLD_SEARCH_MAX_ROWS),
                100,
                500000,
                'cold search max rows',
            ),
            'cold_search_max_bytes' => $this->boundedInteger(
                config('log_archive.cold_search_max_bytes', self::COLD_SEARCH_MAX_BYTES),
                1024 * 1024,
                4 * 1024 * 1024 * 1024,
                'cold search max bytes',
            ),
        ];
    }

    /** @param  list<string>  $tables @return array<string, string> */
    private function resolveTables(array $tables): array
    {
        $configured = (array) config('log_archive.tables', []);
        $excluded = array_values((array) config('log_archive.excluded_tables', []));

        if ($tables === []) {
            foreach (array_keys($configured) as $table) {
                if (in_array($table, $excluded, true)) {
                    throw new InvalidArgumentException("{$table} is an audit/financial table and cannot be archived by this command.");
                }
            }

            return $configured;
        }

        $resolved = [];
        foreach ($tables as $table) {
            $table = trim((string) $table);
            if ($table === '') {
                continue;
            }
            if (in_array($table, $excluded, true)) {
                throw new InvalidArgumentException("{$table} is an audit/financial table and cannot be archived by this command.");
            }
            if (! array_key_exists($table, $configured)) {
                throw new InvalidArgumentException("Unsupported log archive table: {$table}");
            }
            $resolved[$table] = (string) $configured[$table];
        }

        if ($resolved === []) {
            throw new InvalidArgumentException('At least one supported log table is required.');
        }

        return $resolved;
    }

    private function partPath(ArchiveItem $item, array $config): string
    {
        $dir = $this->v2Directory($item->batch_id, $config, $item->created_at?->format('Y-m'));

        return $dir.DIRECTORY_SEPARATOR.$item->table_name.'-'.$item->id_min.'-'.$item->id_max.'.part';
    }

    private function publishedPath(ArchiveItem $item, array $config): string
    {
        $dir = $this->v2Directory($item->batch_id, $config, $item->created_at?->format('Y-m'));

        return $dir.DIRECTORY_SEPARATOR.$item->table_name.'-'.$item->id_min.'-'.$item->id_max.'.csv';
    }

    private function manifestPath(ArchiveItem $item, array $config): string
    {
        $dir = $this->v2Directory($item->batch_id, $config, $item->created_at?->format('Y-m'));

        return $dir.DIRECTORY_SEPARATOR.$item->table_name.'-'.$item->id_min.'-'.$item->id_max.'.manifest.json';
    }

    /** @param  array<string, mixed>  $config */
    private function v2Directory(string $batchId, array $config, ?string $month = null): string
    {
        $month = $month !== null && preg_match('/^\d{4}-\d{2}$/', $month) === 1
            ? $month
            : CarbonImmutable::now()->format('Y-m');
        if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $batchId)) {
            throw new InvalidArgumentException('Invalid archive batch id.');
        }

        return rtrim((string) $config['archive_root'], DIRECTORY_SEPARATOR.'/\\')
            .DIRECTORY_SEPARATOR.'v2'.DIRECTORY_SEPARATOR.$month.DIRECTORY_SEPARATOR.$batchId;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }
        @chmod($directory, 0750);
    }

    private function boundedInteger(mixed $value, int $minimum, int $maximum, string $label): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException("{$label} must be an integer.");
        }
        $value = (int) $value;
        if ($value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException("{$label} must be between {$minimum} and {$maximum}.");
        }

        return $value;
    }

    /** @return resource */
    private function acquireGlobalLock(): mixed
    {
        $path = storage_path('framework/cache/log-archive.lock');
        $this->ensureDirectory(dirname($path));
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException("Unable to open archive lock file: {$path}");
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Another log archive process is already running.');
        }

        return $handle;
    }
}
