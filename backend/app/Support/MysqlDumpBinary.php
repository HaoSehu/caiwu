<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * mysqldump 可执行文件探测。
 *
 * 从 DatabaseStatusService 的私有实现抽出，供管理端下载备份与升级前备份共用，
 * 避免两处各自维护候选路径而出现「一处能备份、另一处报找不到」的不一致。
 * 使用 config() 而非 env()：config:cache 启用后 env() 读不到 .env，但 config() 仍可取到缓存值。
 */
final class MysqlDumpBinary
{
    /**
     * 候选路径，按优先级从高到低。
     *
     * @return list<string>
     */
    public static function candidates(): array
    {
        return array_values(array_filter([
            config('database.mysqldump_path'),
            'D:\\BtSoft\\mysql\\MySQL8.0\\bin\\mysqldump.exe',
            'D:\\BtSoft\\mysql\\MySQL5.7\\bin\\mysqldump.exe',
            '/www/server/mysql/bin/mysqldump',
            '/usr/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            'mysqldump',
        ], static fn (mixed $path): bool => is_string($path) && trim($path) !== ''));
    }

    /**
     * 解析可执行的 mysqldump 路径。
     *
     * @throws RuntimeException 所有候选均不可用时抛出
     */
    public static function resolve(): string
    {
        foreach (self::candidates() as $candidate) {
            $path = trim($candidate);
            if ($path === 'mysqldump') {
                return $path;
            }

            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('未找到 mysqldump，请配置 MYSQLDUMP_PATH 或安装 MySQL 客户端');
    }
}
