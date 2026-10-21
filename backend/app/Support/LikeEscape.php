<?php

declare(strict_types=1);

namespace App\Support;

/**
 * LIKE 关键词转义。
 *
 * 现状痛点：全库约 40+ 处手写 '%'.$keyword.'%'，关键词中的 % 与 _ 未转义，
 * 用户搜 "%%%" 会退化成全表扫描，搜 "a_b" 会误匹配 "axb"。
 * 这里统一转义并给出可直接传入 where 的三种形态。
 */
final class LikeEscape
{
    /**
     * 转义 LIKE 元字符（配合 ESCAPE '\\' 使用）。
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
