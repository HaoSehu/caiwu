<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\DatabaseUpgradeService;
use App\Support\Upgrade\VersionManifest;
use Illuminate\Console\Command;
use Throwable;

/**
 * 受控数据库升级入口：唯一允许在生产执行核心迁移的命令。
 *
 * 与直接执行 `php artisan migrate` 的区别：
 * 1. 先取互斥锁，避免定时任务与人工升级并发执行；
 * 2. 校验代码版本与数据库版本，拒绝降级和跨版本直跳；
 * 3. 拒绝代码包中缺失且不在白名单内的迁移记录；
 * 4. 生产环境强制先做全库备份；
 * 5. 全过程写入 system_upgrade_runs，失败时保留摘要且不推进数据库版本。
 */
class DatabaseUpgradeCommand extends Command
{
    protected $signature = 'db:upgrade
        {--force : 生产环境非交互执行时确认已完成必要准备}
        {--dry-run : 只打印将执行的 SQL，不写库也不写升级记录}
        {--skip-backup : 跳过升级前备份（production 环境禁止）}
        {--adopt= : 首次采纳既有数据库时指定的版本，必须等于当前代码包版本}';

    protected $description = '执行受控数据库升级（版本校验 + 备份 + 迁移 + 记账）';

    public function handle(DatabaseUpgradeService $upgrades): int
    {
        $adopt = $this->option('adopt');
        $adoptVersion = is_string($adopt) && trim($adopt) !== '' ? trim($adopt) : null;
        $dryRun = (bool) $this->option('dry-run');
        $skipBackup = (bool) $this->option('skip-backup');

        if (! $dryRun && ! (bool) $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->error('非交互模式必须显式传入 --force');

                return self::FAILURE;
            }

            if (! $this->confirm('已确认完成数据库备份并进入维护窗口，是否继续升级？', false)) {
                $this->line('已取消数据库升级');

                return self::SUCCESS;
            }
        }

        if ($skipBackup && app()->environment('production')) {
            $this->error('production 环境禁止使用 --skip-backup：升级前必须完成数据库备份');

            return self::FAILURE;
        }

        try {
            $manifest = VersionManifest::load();
            $result = $upgrades->upgrade($manifest, $dryRun, $skipBackup, $adoptVersion);
        } catch (Throwable $exception) {
            $this->error('数据库升级失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        // dry-run 的 message 是 migrator 的原始输出，已含「Nothing to migrate」等提示，
        // 不再额外包一层 <info> 重复打印。
        if ($result['status'] !== 'dry-run') {
            $this->line('<info>'.$result['message'].'</info>');
        }

        if ($result['backup'] !== null) {
            $this->line('升级前备份：'.$result['backup']);
        }

        if ($result['executed'] !== []) {
            $this->line('本次执行迁移（'.count($result['executed']).'）：');
            foreach ($result['executed'] as $name) {
                $this->line('  - '.$name);
            }
        }

        if ($result['status'] === 'dry-run') {
            $this->line($result['message']);
        }

        return self::SUCCESS;
    }
}
