<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 分页上限的单一真源。
 *
 * 过去同一个 page_size 在三个位置被三种上限处理：Request 校验规则默认放行 100、
 * ClientFormRequest::perPage() 硬钳 50、服务层再自钳 100，导致 51–100 的实际行为
 * 取决于调用路径（同一参数出现不同结果）。上限统一收敛到本类，校验与提取共用同一常量。
 */
final class Pagination
{
    /** 单页最小条数。 */
    public const MIN_PAGE_SIZE = 1;

    /**
     * 单页最多返回的条数。
     *
     * 前端已按 100 理解契约：控制台穷尽拉取列表与工单回复都用 page_size=100，
     * 因此不能向下收紧到 50（会直接触发 422 或静默截断）。
     */
    public const MAX_PAGE_SIZE = 100;

    /**
     * 把请求里的 page_size 收敛到合法区间。
     *
     * 只统一上限，默认值仍由调用方按业务给（Client 15 / Admin 20 / 内容 12 等）。
     */
    public static function clamp(int $pageSize, int $max = self::MAX_PAGE_SIZE): int
    {
        return max(self::MIN_PAGE_SIZE, min($pageSize, $max));
    }
}
