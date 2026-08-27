<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ContentExcerpt;
use App\Support\EmailMasker;
use PHPUnit\Framework\TestCase;

final class SupportMaskAndExcerptTest extends TestCase
{
    public function test_mask_keeps_first_char_and_domain(): void
    {
        $this->assertSame('z***@example.com', EmailMasker::mask('zhang.san@example.com'));
    }

    public function test_mask_handles_empty_and_null_and_local(): void
    {
        $this->assertSame('', EmailMasker::mask(null));
        $this->assertSame('', EmailMasker::mask('   '));
        $this->assertSame('***@domain.com', EmailMasker::mask('@domain.com'));
    }

    public function test_mask_passthrough_without_at_sign(): void
    {
        $this->assertSame('not-an-email', EmailMasker::mask('not-an-email'));
    }

    public function test_mask_keeps_multibyte_first_char(): void
    {
        $this->assertSame('张***@mail.cn', EmailMasker::mask('张三丰@mail.cn'));
    }

    public function test_excerpt_renders_markdown_heading_and_collapses_whitespace(): void
    {
        // Str::limit 链路保留 markdown 块级元素转换出的边界空格，行为与历史线上实现一致
        $excerpt = ContentExcerpt::fromMarkdown("# 标题\n\n正文第一段，包含 <b>粗体</b> 与多行   空白。");

        $this->assertSame('标题 正文第一段，包含 粗体 与多行 空白。 ', $excerpt);
    }

    public function test_excerpt_truncates_long_content_with_suffix(): void
    {
        $excerpt = ContentExcerpt::fromMarkdown(str_repeat("段落文字。\n\n", 60));

        // Str::limit 按 mb_strwidth 截断（全角按 2 计）：多段落文本被压缩至少量段落并带省略号
        $this->assertTrue(str_ends_with($excerpt, '...'));
        $segmentCount = substr_count($excerpt, '段落文字。');
        $this->assertGreaterThan(1, $segmentCount);
        $this->assertLessThan(60, $segmentCount);
    }

    public function test_excerpt_never_leaves_raw_html_tags(): void
    {
        $excerpt = ContentExcerpt::fromMarkdown("<script>alert(1)</script>\n\n<b>加粗</b>文本");

        $this->assertStringNotContainsString('<script>', $excerpt);
        $this->assertStringNotContainsString('<b>', $excerpt);
        $this->assertStringContainsString('alert(1)', $excerpt);
        $this->assertStringContainsString('加粗', $excerpt);
    }

    public function test_excerpt_of_empty_input_is_empty(): void
    {
        $this->assertSame('', ContentExcerpt::fromMarkdown(''));
    }
}
