<?php

declare(strict_types=1);

namespace App\Services\MassSend;

/**
 * 群发/关怀消息渲染器（M9 与 M12 共用）。
 *
 * 以 {{var}} 占位符做用户维度变量替换；未知占位符原样保留。
 * 通道级最小转义：邮件正文为 HTML，渲染时对变量值（用户提供内容，如 nickname）
 * 做 htmlspecialchars(ENT_QUOTES) 防止经 {{nickname}} 注入标签；纯文本通道
 * （站内信正文、邮件主题等）不转义，保持原值。
 */
final class MassMessageRenderer
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public static function render(string $template, array $variables, bool $escapeHtml = false): string
    {
        if ($template === '') {
            return '';
        }

        $replacements = [];

        foreach ($variables as $key => $value) {
            $text = (string) $value;
            $replacements['{{'.trim((string) $key).'}}'] = $escapeHtml
                ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8', false)
                : $text;
        }

        return strtr($template, $replacements);
    }

    /**
     * 用户维度基础变量（site_name/date 为任务级变量，可被调用方覆盖）。
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    public static function baseVariables(string $nickname, string $email, array $extra = []): array
    {
        return $extra + [
            'nickname' => $nickname !== '' ? $nickname : '客户',
            'email' => $email,
            'site_name' => (string) config('idc.site_name', config('app.name', '服务商')),
            'date' => now()->format('Y-m-d'),
        ];
    }
}
