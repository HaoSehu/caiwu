<?php

declare(strict_types=1);

namespace App\Support\Upgrade;

use RuntimeException;

/**
 * schema baseline 一致性检查。
 *
 * 基线 `database/schema/mysql-schema.sql` 是新环境初始化的唯一结构来源，
 * 一旦它滞后于实库，新装环境就会缺表缺列，而问题要到很久以后才暴露。
 * 因此把「基线声明的表数/迁移数」与「当前实库」做只读比对。
 */
final class BaselineInspector
{
    /**
     * 读取 baseline-meta.json。
     *
     * @param  string  $metaPath  元数据文件绝对路径
     * @return array{table_count: int, migration_count: int, exported_at: string, database: string}|null
     *                                                                                                   元数据不存在时返回 null
     *
     * @throws RuntimeException 元数据存在但结构非法时抛出
     */
    public function metadata(string $metaPath): ?array
    {
        if (! is_file($metaPath)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($metaPath), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('baseline-meta.json 不是合法 JSON：'.$metaPath, 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('baseline-meta.json 结构必须为对象：'.$metaPath);
        }

        return [
            'table_count' => (int) ($decoded['table_count'] ?? 0),
            'migration_count' => (int) ($decoded['migration_count'] ?? 0),
            'exported_at' => (string) ($decoded['exported_at'] ?? ''),
            'database' => (string) ($decoded['database'] ?? ''),
        ];
    }

    /**
     * 计算基线与实库的差异。
     *
     * @param  string  $baselinePath  mysql-schema.sql 绝对路径
     * @return array{
     *     available: bool,
     *     baseline_tables: int,
     *     baseline_migrations: int,
     *     live_tables: int,
     *     live_migrations: int,
     *     drifted: bool
     * }
     *
     * @throws RuntimeException 基线文件不可读时抛出
     */
    public function compare(string $baselinePath, int $liveTables, int $liveMigrations): array
    {
        if (! is_file($baselinePath)) {
            return [
                'available' => false,
                'baseline_tables' => 0,
                'baseline_migrations' => 0,
                'live_tables' => $liveTables,
                'live_migrations' => $liveMigrations,
                'drifted' => true,
            ];
        }

        $sql = (string) file_get_contents($baselinePath);
        $baselineTables = preg_match_all('/^CREATE TABLE /m', $sql);
        $baselineMigrations = preg_match_all('/INSERT INTO `migrations` /', $sql);

        return [
            'available' => true,
            'baseline_tables' => (int) $baselineTables,
            'baseline_migrations' => (int) $baselineMigrations,
            'live_tables' => $liveTables,
            'live_migrations' => $liveMigrations,
            'drifted' => (int) $baselineTables !== $liveTables || (int) $baselineMigrations !== $liveMigrations,
        ];
    }
}
