<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * 重建测试数据库（干净检出后自包含跑测试的第一步）。
 *
 * 双闸防呆：
 * 1. 目标库名必须以 `_test` 结尾（拒绝操作 idc 等非测试库）；
 * 2. 必须显式传 `--force`（缺省仅打印备份提醒并拒绝）。
 *
 * 防护边界：production 环境一律拒绝；重建前打印 mysqldump 备份提醒（不自动备份）；
 * 破坏性操作前预检 `mysql` 客户端（migrate:fresh 加载 schema dump 依赖它），
 * 缺失时可在删表前提前失败，避免留下半程状态。
 */
class TestPrepareCommand extends Command
{
    protected $signature = 'test:prepare
        {--target=idc_test : 目标测试库名（必须以 _test 结尾）}
        {--mysql-bin= : MySQL 客户端 bin 目录（可选，如 D:/BtSoft/mysql/MySQL8.0/bin；缺省从 PATH 查找 mysql）}
        {--force : 确认执行重建（缺省拒绝）}';

    protected $description = '重建测试数据库（migrate:fresh），供干净检出后自包含地运行全量测试；双闸保护：_test 后缀 + --force';

    public function handle(): int
    {
        $target = trim((string) $this->option('target'));

        try {
            if ($this->getLaravel()->environment('production')) {
                throw new RuntimeException('禁止在 production 环境执行测试库重建。');
            }

            if (! str_ends_with($target, '_test')) {
                throw new RuntimeException("目标库名「{$target}」必须以 _test 结尾：本命令仅用于测试库重建，拒绝操作其他数据库。");
            }

            if (! (bool) $this->option('force')) {
                $this->error('缺少 --force：本操作会清空目标测试库的全部数据。');
                $this->line('建议先备份：mysqldump '.$target.' > backup-'.date('Ymd-His').'.sql');
                $this->line('确认后执行：php artisan test:prepare --force --target='.$target);

                return self::FAILURE;
            }

            $this->warn('即将重建测试库「'.$target.'」：全部表将被删除并重新迁移。');
            $this->line('如未备份，建议先执行：mysqldump '.$target.' > backup-'.date('Ymd-His').'.sql');
            $this->newLine();

            // 破坏性操作前预检：mysql 客户端缺失时在删表前失败，避免半程状态。
            $this->prepareMysqlClient((string) $this->option('mysql-bin'));

            $connection = (string) config('database.default');
            $original = config("database.connections.{$connection}.database");
            config(["database.connections.{$connection}.database" => $target]);
            DB::purge($connection);

            try {
                // 连通性探测：目标库不存在或无权限会在此失败，避免进入半程。
                DB::connection($connection)->getPdo();
            } catch (Throwable $exception) {
                throw new RuntimeException("目标库「{$target}」不可连接：".$exception->getMessage(), 0, $exception);
            }

            $this->info('开始重建（migrate:fresh）...');
            $exitCode = $this->call('migrate:fresh', ['--force' => true]);

            if ($exitCode !== self::SUCCESS) {
                throw new RuntimeException(
                    'migrate:fresh 失败，请检查上方迁移链输出；测试库可能处于半程状态，'
                    .'可用 storage/app/backups/ 下最近备份恢复后重试。'
                );
            }

            $tableCount = count(DB::connection($connection)->select('SHOW TABLES'));

            $this->newLine();
            $this->info("测试库「{$target}」重建完成：{$tableCount} 张表。");
            $this->line('下一步：cd backend && php artisan test');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            // 恢复默认连接配置，避免影响同进程后续操作。
            if (isset($connection, $original)) {
                config(["database.connections.{$connection}.database" => $original]);
                DB::purge($connection);
            }
        }
    }

    /**
     * 预检 mysql 客户端（`migrate:fresh` 加载 schema dump 时硬编码调用 `mysql`，走 PATH 查找）。
     *
     * 指定 `--mysql-bin` 时把该目录临时加入当前进程 PATH（同步 putenv/$_SERVER/$_ENV，
     * 确保 Symfony Process 派生的子进程继承）；随后用 `mysql --version` 探针确认可达。
     */
    private function prepareMysqlClient(string $mysqlBin): void
    {
        $mysqlBin = trim($mysqlBin);

        if ($mysqlBin !== '') {
            $binDir = rtrim($mysqlBin, '/\\');
            $binary = $binDir.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'mysql.exe' : 'mysql');

            if (! is_file($binary)) {
                throw new RuntimeException("--mysql-bin 目录内未找到 mysql 客户端：{$binary}");
            }

            // 末尾追加而非前插：避免指定目录内同名可执行文件遮蔽系统 PATH 中的既有命令。
            $path = ((string) getenv('PATH')).PATH_SEPARATOR.$binDir;
            putenv('PATH='.$path);

            // Symfony Process 在 Windows 下用 $_SERVER/$_ENV 参与子进程环境合并，需同步更新。
            foreach (array_keys($_SERVER) as $key) {
                if (strcasecmp((string) $key, 'PATH') === 0) {
                    $_SERVER[$key] = $path;
                }
            }
            foreach (array_keys($_ENV) as $key) {
                if (strcasecmp((string) $key, 'PATH') === 0) {
                    $_ENV[$key] = $path;
                }
            }

            $this->line("已为本次执行临时加入 MySQL 客户端目录：{$binDir}");
        }

        try {
            $probe = Process::fromShellCommandline('mysql --version');
            $probe->setTimeout(30);
            $probe->mustRun();
            $version = trim($probe->getOutput());
        } catch (Throwable $exception) {
            throw new RuntimeException(
                '未找到 mysql 客户端（migrate:fresh 加载 schema dump 必需）。'
                .'请安装 MySQL 客户端并加入 PATH，或用 --mysql-bin="D:/path/to/mysql/bin" 指定后重试。',
                0,
                $exception
            );
        }

        $this->line('MySQL 客户端：'.$version);
    }
}
