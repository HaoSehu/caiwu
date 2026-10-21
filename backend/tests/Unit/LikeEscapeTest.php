<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LikeEscape;
use PHPUnit\Framework\TestCase;

/**
 * LIKE 关键词转义：% 与 _ 必须转义，否则用户输入会退化为通配并放大全表扫描。
 */
class LikeEscapeTest extends TestCase
{
    public function test_escapes_percent_and_underscore(): void
    {
        $this->assertSame('%a\\_b\\%c%', LikeEscape::contains('a_b%c'));
        $this->assertSame('%50\\%%', LikeEscape::contains('50%'));
    }

    public function test_escapes_backslash_first(): void
    {
        // 反斜杠必须先转义，否则会把后面的转义序列吃掉
        $this->assertSame('%a\\\\b%', LikeEscape::contains('a\\b'));
    }

    public function test_plain_keyword_is_unchanged(): void
    {
        $this->assertSame('%张三%', LikeEscape::contains('张三'));
        $this->assertSame('张三%', LikeEscape::startsWith('张三'));
        $this->assertSame('%张三', LikeEscape::endsWith('张三'));
    }
}
