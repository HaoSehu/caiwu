<?php

declare(strict_types=1);

namespace App\Support;

/**
 * LIKE 关键词转义。
 *
 * 背景：历史上全库散落 40+ 处手写 '%'.$keyword.'%'，关键词中的 % 与 _ 未转义，
 * 用户搜 "%%%" 会退化成全表扫描，搜 "a_b" 会误匹配 "axb"。
 * 全库裸拼现已清零（2026-10-21 收敛），新增查询一律经这里转义并取三种形态之一。
 */
final class LikeEscape
{
    /**
     * 转义 LIKE 元字符。
     *
     * 依赖 MySQL 默认的反斜杠转义语义（sql_mode 不含 NO_BACKSLASH_ESCAPES）；
     * 若将来切到 NO_BACKSLASH_ESCAPES，所有调用方需同步给 LIKE 补 ESCAPE '\\' 子句。
     */
    public static function escape(string $keyword): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
    }

    /**
     * 包含匹配模式：%keyword%。
     */
    public static function contains(string $keyword): string
    {
        return '%'.self::escape($keyword).'%';
    }

    /**
     * 前缀匹配模式：keyword%。
     */
    public static function startsWith(string $keyword): string
    {
        return self::escape($keyword).'%';
    }

    /**
     * 后缀匹配模式：%keyword。
     */
    public static function endsWith(string $keyword): string
    {
        return '%'.self::escape($keyword);
    }
}
