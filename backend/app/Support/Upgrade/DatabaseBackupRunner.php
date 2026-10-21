<?php

declare(strict_types=1);

namespace App\Support\Upgrade;

use App\Support\MysqlDumpBinary;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * 升级前数据库备份。
 *
 * 与管理端「导出备份」的区别：导出备份为了可读与体积会排除 6 张高增长日志表，
 * 但升级可能改动这些表的结构（例如补索引、加生成列），因此升级备份必须导出全库，
 * 不做任何表排除，否则结构回退时会缺表。
 */
final class DatabaseBackupRunner
{
    private const BACKUP_SUBDIR = 'upgrade-backups';

    private const TIMEOUT_SECONDS = 1800;

    /**
     * 创建全库备份。
     *
     * @param  string  $destination  目标目录，缺省 storage/app/private/upgrade-backups
     * @return string 备份文件的绝对路径
     *
     * @throws RuntimeException 备份命令失败、产物为空或缺少建表语句时抛出
     */
    public function create(?string $destination = null): string
    {
        $config = (array) config('database.connections.'.config('database.default'));
        $database = (string) ($config['database'] ?? '');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $socket = (string) ($config['unix_socket'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('数据库连接配置不完整，无法执行升级前备份');
        }

        $directory = $destination ?? storage_path('app/private/'.self::BACKUP_SUBDIR);
        File::ensureDirectoryExists($directory, 0750);

        $filename = sprintf('upgrade_%s_%s.sql', date('YmdHis'), bin2hex(random_bytes(3)));
        $absolutePath = rtrim($directory, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.$filename;

        $command = [
            MysqlDumpBinary::resolve(),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '--result-file='.$absolutePath,
        ];

        if ($socket !== '') {
            array_push($command, '--socket='.$socket);
        } else {
            array_push($command, '--host='.$host, '--port='.(string) $port);
        }
        array_push($command, '--user='.$username, $database);

        $result = Process::timeout(self::TIMEOUT_SECONDS)
            ->env(['MYSQL_PWD' => $password])
            ->run($command);

        if (! $result->successful()) {
            $this->cleanup($absolutePath);

            throw new RuntimeException('升级前数据库备份失败：'.trim($result->errorOutput() ?: $result->output()));
        }

        if (! is_file($absolutePath) || filesize($absolutePath) === 0) {
            $this->cleanup($absolutePath);

            throw new RuntimeException('升级前数据库备份产物为空：'.$absolutePath);
        }

        // 产物必须包含建表语句，否则说明 mysqldump 只导出了数据或提前中断。
        $head = (string) file_get_contents($absolutePath, false, null, 0, 2 * 1024 * 1024);
        if (! str_contains($head, 'CREATE TABLE')) {
            $this->cleanup($absolutePath);

            throw new RuntimeException('升级前数据库备份不完整（缺少 CREATE TABLE）：'.$absolutePath);
        }

        return $absolutePath;
    }

    /**
     * 删除本次生成的残缺备份。
     */
    private function cleanup(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
