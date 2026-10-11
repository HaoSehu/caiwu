<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\SmsProviderTemplate;
use App\Support\SchemaMetadataCache;

/**
 * 短信运营商模板解析器（短信模板管理机制 2026-10-11）。
 *
 * 职责单一：把业务模板码解析为「当前绑定 driver 下可用的运营商模板映射行」。
 * 只返回 status=approved 的映射（pending/rejected/deleted 一律视同无映射，
 * 审核状态因此成为解析器的内部输入，发送侧与调用方无需感知）。
 *
 * 解析优先级（SmsService 负责编排，本类只负责映射层一环）：
 * 1. options 显式 provider_template_id（人工指定，最高）
 * 2. 本类：映射表 approved 行（plugin_key=driverKey AND business_code=业务码，
 *    synced_at 最新、并列按 id）
 * 3. notification_templates.provider_template_id 旧列（人工兜底，跨运营商
 *    风险见执行计划决策日志）
 * 4. options fallback_provider_template_id（verify-code 遗留链）
 * 5. 业务码原样
 *
 * 缓存为实例级（每请求/每 worker 进程），键 `driverKey:businessCode`；
 * 未命中存 false 防止重复查询。与 NotificationTemplateService 的实例缓存
 * 同一口径。
 */
final class SmsTemplateResolver
{
    /**
     * @var array<string, SmsProviderTemplate|false>
     */
    private array $mappingCache = [];

    /**
     * 解析业务码在指定 driver 下的 approved 运营商模板映射行。
     *
     * @return SmsProviderTemplate|null null=无可用映射（调用方走回落链）
     */
    public function resolve(string $businessCode, ?string $driverKey): ?SmsProviderTemplate
    {
        $businessCode = trim($businessCode);
        $driverKey = trim((string) $driverKey);

        if ($businessCode === '' || $driverKey === '') {
            return null;
        }

        if (! SchemaMetadataCache::hasTable('sms_provider_templates')) {
            return null;
        }

        $cacheKey = $driverKey.':'.$businessCode;
        if (array_key_exists($cacheKey, $this->mappingCache)) {
            $cached = $this->mappingCache[$cacheKey];

            return $cached instanceof SmsProviderTemplate ? $cached : null;
        }

        $mapping = SmsProviderTemplate::query()
            ->where('plugin_key', $driverKey)
            ->where('business_code', $businessCode)
            ->where('status', SmsProviderTemplate::STATUS_APPROVED)
            ->orderByDesc('synced_at')
            ->orderByDesc('id')
            ->first();

        $this->mappingCache[$cacheKey] = $mapping ?? false;

        return $mapping;
    }
}
