<?php

declare(strict_types=1);

namespace App\Support;

/**
 * activity_logs 日志流分类（对齐 docs/设计文档/后端/日志与归档协同重构方案.md）。
 * 写入器与 operation_logs 回填共用同一推导，保证历史与新增数据的 stream 语义一致。
 */
class ActivityLogStream
{
    public const ACCESS = 'access';

    public const AUTH = 'auth';

    public const BUSINESS = 'business';

    public const SCHEDULE = 'schedule';

    private const HTTP_ACTION_PATTERN = '/^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD) /';

    public static function resolve(string $module, string $action): string
    {
        if (preg_match(self::HTTP_ACTION_PATTERN, $action) === 1) {
            return self::ACCESS;
        }

        if ($module === 'auth' && $action === 'admin.login') {
            return self::AUTH;
        }

        return self::BUSINESS;
    }
}
