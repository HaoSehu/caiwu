<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\DatabaseUpgradeService;
use App\Support\Upgrade\VersionManifest;
use Illuminate\Console\Command;
use Throwable;

/**
 * 只读查看代码版本、数据库版本与迁移清单一致性。
 *
 * 退出码约定：0 一致；2 有待执行迁移 / 非白名单孤儿 / 版本不一致；1 执行错误。
 * 该约定使它可以安全地放进部署脚本做前置判断，而不会把「待升级」误判成「失败」。
 */
class DatabaseUpgradeStatusCommand extends Command
{
    protected $signature = 'db:upgrade-status {--json : 以 JSON 输出完整状态}';

    protected $description = '只读查看代码版本、数据库版本与待执行迁移';

    public function handle(DatabaseUpgradeService $upgrades): int
    {
        try {
            $manifest = VersionManifest::load();
            $status = $upgrades->status($manifest);
        } catch (Throwable $exception) {
            $this->error('升级状态读取失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->renderHuman($status);
        }

        return $status['requires_attention'] ? 2 : self::SUCCESS;
    }

    /**
     * 以人类可读格式输出状态。
     *
     * @param  array{code_version: string, database_version: ?string, adopted: bool, pending: list<string>, allowed_missing: list<string>, unexpected_missing: list<string>, baseline: array{available: bool, baseline_tables: int, baseline_migrations: int, live_tables: int, live_migrations: int, drifted: bool}, latest_run: ?array<string, mixed>, requires_attention: bool}  $status
     */
    private function renderHuman(array $status): void
    {
        $this->line('代码版本：<info>'.$status['code_version'].'</info>');
        $this->line('数据库版本：<info>'.($status['database_version'] ?? '未采纳').'</info>');
        $this->line('待执行迁移：<comment>'.count($status['pending']).'</comment>');
        $this->line('白名单内孤儿迁移：'.count($status['allowed_missing']));
        $this->line('非白名单孤儿迁移：<comment>'.count($status['unexpected_missing']).'</comment>');

        if ($status['pending'] !== []) {
            $this->line('待执行清单：'.implode(', ', $status['pending']));
        }

        if ($status['unexpected_missing'] !== []) {
            $this->error('代码包缺失且不在白名单的迁移：'.implode(', ', $status['unexpected_missing']));
        }

        $baseline = $status['baseline'];
        if (! $baseline['available']) {
            $this->error('schema baseline 文件不存在：database/schema/mysql-schema.sql');
        } elseif ($baseline['drifted']) {
            $this->error(sprintf(
                'schema baseline 已滞后：基线 %d 表 / %d 迁移，实库 %d 表 / %d 迁移；请执行 php backend/scripts/export_schema_baseline.php',
                $baseline['baseline_tables'],
                $baseline['baseline_migrations'],
                $baseline['live_tables'],
                $baseline['live_migrations'],
            ));
        } else {
            $this->line(sprintf('schema baseline：一致（%d 表 / %d 迁移）', $baseline['baseline_tables'], $baseline['baseline_migrations']));
        }

        $latest = $status['latest_run'];
        if ($latest !== null) {
            $this->line(sprintf(
                '最近一次升级：%s %s → %s（%s）',
                (string) ($latest['status'] ?? ''),
                (string) ($latest['from_version'] ?? 'NULL'),
                (string) ($latest['to_version'] ?? ''),
                (string) ($latest['started_at'] ?? ''),
            ));
        }
    }
}
