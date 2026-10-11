<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins\Support;

/**
 * 插件探测类动作的请求级结果缓存。
 *
 * 以 scoped 单例注册（PluginServiceProvider）：框架在 HTTP 响应结束后、队列任务
 * 处理前后会 flush scoped 实例，缓存天然只在当前请求/任务内存活，不会跨请求读到
 * 旧值；插件配置保存后同一请求内不需要感知（探测动作本来只服务列表/匹配判断）。
 *
 * 只缓存成功结果——失败不缓存，插件恢复后下一次调用立即生效。
 */
final class PluginProbeMemo
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $entries = [];

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        $entry = $this->entries[$key] ?? null;

        return is_array($entry) ? $entry : null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function put(string $key, array $response): void
    {
        $this->entries[$key] = $response;
    }
}
