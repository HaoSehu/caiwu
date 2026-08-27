<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * 公开内容摘要：在写入侧固化生成，公开列表查询不再对完整正文实时渲染 Markdown。
 */
class ContentExcerpt
{
    public static function fromMarkdown(string $markdown): string
    {
        return Str::limit(
            preg_replace('/\s+/u', ' ', strip_tags(Str::markdown($markdown))) ?: '',
            120,
            '...'
        );
    }
}
