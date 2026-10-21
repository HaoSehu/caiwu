<?php

declare(strict_types=1);

namespace App\Support\Upgrade;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 升级账本读写。
 *
 * 只做两件事：读取 `system_installation` 的数据库版本、以及记录一次升级的完整生命周期。
 * 不承担版本校验与迁移执行，避免账本层混入业务判断。
 */
final class UpgradeLedger
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RECOVERY_REQUIRED = 'recovery_required';

    public const STATUS_ADOPTED = 'adopted';

    /**
     * 数据库已升级到的版本；未采纳/未安装时返回 null。
     */
    public function currentVersion(): ?string
    {
        if (! $this->installationTableExists()) {
            return null;
        }

        $value = DB::table('system_installation')->where('id', 1)->value('app_version');

        return $value === null ? null : (string) $value;
    }

    /**
     * 是否已完成首次采纳。
     */
    public function isAdopted(): bool
    {
        return $this->installationTableExists() && DB::table('system_installation')->where('id', 1)->exists();
    }

    /**
     * 首次采纳既有数据库：写入当前版本但不执行迁移。
     *
     * @param  string  $version  与数据库结构一致的版本号
     * @param  int|null  $runId  采纳时创建的升级记录主键，用于同步状态；无需关联时传 null
     *
     * @throws RuntimeException 账本表不存在时抛出
     */
    public function adopt(string $version, ?int $runId = null): void
    {
        $this->assertInstallationTable();

        DB::transaction(function () use ($version, $runId): void {
            $now = now();
            DB::table('system_installation')->updateOrInsert(
                ['id' => 1],
                [
                    'app_version' => $version,
                    'adopted_at' => $now,
                    'upgraded_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            if ($runId !== null) {
                DB::table('system_upgrade_runs')->where('id', $runId)->update([
                    'status' => self::STATUS_ADOPTED,
                    'finished_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /**
     * 创建一条 running 记录。
     *
     * @param  string  $fromVersion  来源版本；首次采纳传空字符串表示 NULL
     * @param  string  $toVersion  目标版本
     * @param  int  $pendingCount  开始时待执行迁移数
     * @param  string|null  $backupPath  备份路径
     * @return int 升级记录主键
     *
     * @throws RuntimeException 账本表不存在时抛出
     */
    public function begin(
        string $fromVersion,
        string $toVersion,
        int $pendingCount,
        ?string $backupPath = null,
    ): int {
        $this->assertRunTable();

        return (int) DB::table('system_upgrade_runs')->insertGetId([
            'from_version' => $fromVersion === '' ? null : $fromVersion,
            'to_version' => $toVersion,
            'status' => self::STATUS_RUNNING,
            'pending_count' => $pendingCount,
            'executed_count' => 0,
            'executed_migrations' => null,
            'backup_path' => $backupPath,
            'error_message' => null,
            'operator' => self::currentOperator(),
            'client_ip' => null,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 标记升级成功，并在同一事务内推进数据库版本。
     *
     * @param  int  $runId  升级记录主键
     * @param  string  $toVersion  目标版本
     * @param  list<string>  $executed  本次执行的迁移名
     *
     * @throws RuntimeException 账本表不存在时抛出
     */
    public function complete(int $runId, string $toVersion, array $executed): void
    {
        $this->assertInstallationTable();
        $this->assertRunTable();

        DB::transaction(function () use ($runId, $toVersion, $executed): void {
            $now = now();
            DB::table('system_upgrade_runs')->where('id', $runId)->update([
                'status' => self::STATUS_COMPLETED,
                'executed_count' => count($executed),
                'executed_migrations' => json_encode($executed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'finished_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('system_installation')->updateOrInsert(
                ['id' => 1],
                [
                    'app_version' => $toVersion,
                    'upgraded_at' => $now,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => $now,
                ],
            );
        });
    }

    /**
     * 标记失败。app_version 不推进。
     *
     * @param  int  $runId  升级记录主键
     * @param  string  $status  failed 或 recovery_required
     * @param  string  $message  失败摘要
     * @param  list<string>  $executed  已成功执行的迁移名
     *
     * @throws RuntimeException 账本表不存在时抛出
     */
    public function fail(int $runId, string $status, string $message, array $executed = []): void
    {
        $this->assertRunTable();

        DB::table('system_upgrade_runs')->where('id', $runId)->update([
            'status' => $status,
            'executed_count' => count($executed),
            'executed_migrations' => $executed === []
                ? null
                : json_encode($executed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error_message' => mb_substr($message, 0, 1000),
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 最近一次升级记录，供 status 命令展示。
     *
     * @return array<string, mixed>|null
     */
    public function latestRun(): ?array
    {
        if (! $this->runTableExists()) {
            return null;
        }

        $row = DB::table('system_upgrade_runs')->orderByDesc('id')->first();

        return $row === null ? null : (array) $row;
    }

    private function installationTableExists(): bool
    {
        return DB::getSchemaBuilder()->hasTable('system_installation');
    }

    private function runTableExists(): bool
    {
        return DB::getSchemaBuilder()->hasTable('system_upgrade_runs');
    }

    /**
     * @throws RuntimeException 升级账本迁移尚未执行时抛出
     */
    private function assertInstallationTable(): void
    {
        if (! $this->installationTableExists()) {
            throw new RuntimeException('升级账本表 system_installation 不存在，请先执行 php artisan db:upgrade --adopt=<版本>');
        }
    }

    /**
     * @throws RuntimeException 升级账本迁移尚未执行时抛出
     */
    private function assertRunTable(): void
    {
        if (! $this->runTableExists()) {
            throw new RuntimeException('升级记录表 system_upgrade_runs 不存在，请先执行 php artisan db:upgrade --adopt=<版本>');
        }
    }

    /**
     * 当前命令行操作者，供审计留痕。
     */
    private static function currentOperator(): string
    {
        if (PHP_SAPI !== 'cli') {
            return 'SYSTEM';
        }

        $user = getenv('USERNAME') ?: getenv('USER') ?: '';

        return $user !== '' ? mb_substr((string) $user, 0, 50) : 'SYSTEM';
    }
}
