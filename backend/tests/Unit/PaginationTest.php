<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Pagination;
use PHPUnit\Framework\TestCase;

/**
 * 分页上限单一真源（M-04）特征测试。
 *
 * 过去 Request 校验默认放行 100、ClientFormRequest::perPage() 硬钳 50、服务层再自钳 100，
 * 同一个 page_size 在 51–100 区间会因调用路径不同返回不同结果。这里锁定统一后的口径。
 */
final class PaginationTest extends TestCase
{
    public function test_max_page_size_allows_console_exhaustive_fetch(): void
    {
        // 控制台穷尽拉取列表与工单回复都用 page_size=100，上限不能收紧到 50。
        $this->assertSame(100, Pagination::MAX_PAGE_SIZE);
        $this->assertSame(100, Pagination::clamp(100));
        $this->assertSame(100, Pagination::clamp(500));
    }

    public function test_clamp_keeps_values_inside_range(): void
    {
        $this->assertSame(1, Pagination::clamp(0));
        $this->assertSame(1, Pagination::clamp(-20));
        $this->assertSame(20, Pagination::clamp(20));
        $this->assertSame(51, Pagination::clamp(51));
    }

    public function test_clamp_honours_explicitly_narrower_max(): void
    {
        // 个别列表有意收窄上限（如余额记录 200 是放宽、通知 50 是收窄），显式传入的 $max 优先。
        $this->assertSame(50, Pagination::clamp(80, 50));
        $this->assertSame(200, Pagination::clamp(500, 200));
    }

    public function test_min_page_size_is_one(): void
    {
        $this->assertSame(1, Pagination::MIN_PAGE_SIZE);
    }
}
