<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 场景变量解析：白名单过滤 + 通用兜底（站点名称、站点 Logo、收件人名称）。
 *
 * 同步发送（MessageDispatcher::sendNow）与异步发布（MessagePublisher::publish）
 * 必须产出完全一致的渲染值，否则同一条业务事件在两条路径下会出现空称呼、
 * 空站点名等差异。此处是唯一实现。
 */
final class MessageVariables
{
    /**
     * @param  array<string, scalar|null>  $variables  调用方传入的业务变量
     * @param  list<string>  $allowed  场景声明的变量白名单
     * @param  string  $recipientName  收件人名称，用作 display_name / recipient_name / customer_name 兜底
     * @return array<string, string>
     */
    public static function resolve(array $variables, array $allowed, string $recipientName = ''): array
    {
        $recipientName = trim($recipientName);

        $values = [];
        foreach ($allowed as $key) {
            $values[$key] = (string) ($variables[$key] ?? '');
        }

        if ($values['site_name'] === '') {
            $values['site_name'] = (string) (SiteConfigPayload::payload()['site_name'] ?? SiteConfigPayload::DEFAULT_SITE_NAME);
        }

        // site_logo 不是场景变量（不进 VARIABLES 白名单），只用于模板的 {{#site_logo}} 区段
        $values['site_logo'] = trim((string) ($variables['site_logo'] ?? ''));
        if ($values['site_logo'] === '') {
            $values['site_logo'] = (string) (SiteConfigPayload::payload()['site_logo'] ?? '');
        }

        $values['customer_name'] = $values['customer_name'] !== ''
            ? $values['customer_name']
            : ($recipientName !== '' ? $recipientName : '客户');

        foreach (MessageTypeCatalog::RECIPIENT_NAME_ALIASES as $alias) {
            if (($values[$alias] ?? '') === '' && $recipientName !== '') {
                $values[$alias] = $recipientName;
            }
        }

        return $values;
    }
}
