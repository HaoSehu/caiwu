<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 消息模板文本渲染：{{变量}} 替换 + {{#区段}}...{{/区段}} 条件块。
 *
 * 邮件模板（历史 notification_templates）使用 Mustache 风格的 logo 区段，
 * 旧 NotificationService 有对应解析；统一消息链路必须保持同一渲染口径，
 * 否则 {{#site_logo}} 会原样泄露到用户邮件正文。
 */
final class MessageTemplateRenderer
{
    /**
     * @param  array<string, string>  $values
     */
    public static function render(string $template, array $values): string
    {
        if ($template === '') {
            return '';
        }

        // 先处理条件区段：值为空时整段剔除，非空时保留内部内容并继续渲染变量。
        $rendered = (string) preg_replace_callback(
            '/\{\{#([a-zA-Z][a-zA-Z0-9_]*)\}\}(.*?)\{\{\/\1\}\}/su',
            static function (array $match) use ($values): string {
                $value = trim((string) ($values[$match[1]] ?? ''));

                return $value === '' ? '' : $match[2];
            },
            $template
        );

        return (string) preg_replace_callback(
            '/\{\{([a-zA-Z][a-zA-Z0-9_]*)\}\}/',
            static fn (array $match): string => $values[$match[1]] ?? '',
            $rendered
        );
    }
}
