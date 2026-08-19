<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * 缓存单次 PHP 进程内不变的表结构元数据，避免列表序列化时反复查询 information_schema。
 */
final class SchemaMetadataCache
{
    /** @var array<string, bool> */
    private static array $tableCache = [];

    /** @var array<string, array<string, true>> */
    private static array $columnCache = [];

    public static function hasTable(string $table, ?string $connection = null): bool
    {
        $connectionName = self::connectionName($connection);
        $key = $connectionName.':'.$table;

        if (array_key_exists($key, self::$tableCache)) {
            return self::$tableCache[$key];
        }

        return self::$tableCache[$key] = $connection === null
            ? Schema::hasTable($table)
            : Schema::connection($connection)->hasTable($table);
    }

    public static function hasColumn(string $table, string $column, ?string $connection = null): bool
    {
        $connectionName = self::connectionName($connection);
        $tableKey = $connectionName.':'.$table;

        if (! self::hasTable($table, $connection)) {
            return false;
        }

        if (! isset(self::$columnCache[$tableKey])) {
            self::$columnCache[$tableKey] = array_fill_keys(
                array_map(
                    static fn (string $name): string => strtolower($name),
                    self::schema($connection)->getColumnListing($table),
                ),
                true,
            );
        }

        return isset(self::$columnCache[$tableKey][strtolower($column)]);
    }

    /** @internal 供迁移型测试和长驻进程中的受控结构变更清空缓存。 */
    public static function flush(): void
    {
        self::$tableCache = [];
        self::$columnCache = [];
    }

    private static function connectionName(?string $connection): string
    {
        return $connection ?: (string) config('database.default', 'default');
    }

    private static function schema(?string $connection): object
    {
        return $connection === null
            ? Schema::getFacadeRoot()
            : Schema::connection($connection);
    }
}
