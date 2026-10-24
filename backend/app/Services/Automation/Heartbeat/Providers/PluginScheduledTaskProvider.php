<?php

declare(strict_types=1);

namespace App\Services\Automation\Heartbeat\Providers;

use App\Models\IntegrationPlugin;
use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\Contracts\ScheduledTaskProvider;
use App\Services\Automation\Heartbeat\ScheduleDeclarationNormalizer;
use App\Services\Automation\Heartbeat\ScheduledTaskValidator;
use App\Services\Automation\ScheduleHookService;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginManifest;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Support\SchemaMetadataCache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PluginScheduledTaskProvider implements ScheduledTaskProvider
{
    public function __construct(
        private Container $container,
        private PluginScanner $scanner,
        private PluginFileLoader $fileLoader,
        private ScheduledTaskValidator $validator,
    ) {}

    /**
     * 插件任务不能因为单个坏清单拖垮心跳注册表；每个插件和每条声明均独立隔离并记日志。
     * 启用阶段已经做强校验，这里是为了保护长驻 Worker 读取到异常文件后的运行时稳定性。
     *
     * @return list<ScheduledTask>
     */
    public function tasks(): array
    {
        config(['idc.schedule_runtime.plugin_tasks' => [
            'status' => 'loaded',
            'error_count' => 0,
        ]]);

        $plugins = $this->enabledPlugins();
        if ($plugins === null) {
            return [];
        }

        $tasks = [];
        $seenKeys = [];

        foreach ($plugins as $plugin) {
            $manifest = $this->findManifest($plugin);
            if (! $manifest instanceof PluginManifest) {
                continue;
            }

            // 与 PluginInstaller 保持一致：清单声明统一按列表处理，兼容单个 class 字符串。
            $definitions = ScheduleDeclarationNormalizer::list($manifest->extra['scheduled_tasks'] ?? []);
            if ($definitions === []) {
                continue;
            }

            try {
                $this->fileLoader->ensureLoaded($manifest);
            } catch (\Throwable $exception) {
                $this->markDegraded();
                Log::warning('[定时任务] 插件任务文件加载失败', $this->pluginContext($manifest, $exception));

                continue;
            }

            foreach ($definitions as $definition) {
                $class = ScheduleDeclarationNormalizer::className($definition);
                if ($class === '') {
                    $this->markDegraded();
                    Log::warning('[定时任务] 插件任务声明缺少 class', $this->pluginContext($manifest));

                    continue;
                }

                try {
                    $task = $this->container->make($class);
                    if (! $task instanceof ScheduledTask) {
                        throw new \InvalidArgumentException('未实现 ScheduledTask 接口');
                    }

                    $this->validator->validate($task, "插件 {$manifest->domain}/{$manifest->slug}");
                    $key = trim($task->key());
                    if (isset($seenKeys[$key])) {
                        throw new \InvalidArgumentException('与已注册插件任务重复：'.$key);
                    }

                    $seenKeys[$key] = true;
                    $tasks[] = $task;
                } catch (\Throwable $exception) {
                    $this->markDegraded();
                    Log::warning('[定时任务] 插件任务实例化或契约校验失败', array_merge(
                        $this->pluginContext($manifest, $exception),
                        ['task_class' => $class],
                    ));
                }
            }
        }

        return $tasks;
    }

    private function findManifest(IntegrationPlugin $plugin): ?PluginManifest
    {
        try {
            $manifest = $this->scanner->find((string) $plugin->domain, (string) $plugin->slug);
            if (! $manifest instanceof PluginManifest) {
                $this->markDegraded();
                Log::warning('[定时任务] 已启用插件的清单不可用，已跳过任务注册', [
                    'domain' => $plugin->domain,
                    'slug' => $plugin->slug,
                ]);
            }

            return $manifest;
        } catch (\Throwable $exception) {
            $this->markDegraded();
            Log::warning('[定时任务] 读取插件清单失败，已跳过任务注册', [
                'domain' => $plugin->domain,
                'slug' => $plugin->slug,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    /**
     * 读取已启用的插件集合，并与 ScheduleHookService 共享同一个作用域内的缓存槽。
     *
     * 为什么不能逐 hook 重复查询：ScheduleHookService 会为 8 个固定 hook 各解析一次插件监听器，
     * 本 Provider 又在同一次总览请求中被两处调用，若每次都重新探测表并重查插件集合，SQL 会随
     * 任务数线性放大。插件启停状态在单次请求内不变，因此两处共用同一份结果。
     *
     * 异常语义保持原样：表不存在时降级并记 warning，查询异常时降级并记 error，且失败结果不写入
     * 缓存槽，后续调用仍会各自重试、各自记录日志，markDegraded() 的计数行为不受影响。
     *
     * @return Collection<int, IntegrationPlugin>|null
     */
    private function enabledPlugins(): ?Collection
    {
        // 取共享槽也在 try 内：槽依赖容器 scoped 注册与 request 解析，
        // HEAD 版 tasks() 的所有查询都在 try 内，这里必须保持同等异常兜底，
        // 否则容器异常会绕过 markDegraded() 直接冒出 tasks()。
        try {
            $slot = ScheduleHookService::enabledPluginsSlot($this->container);

            $cached = $slot->offsetExists('plugins') ? $slot->offsetGet('plugins') : null;
            if ($cached instanceof Collection) {
                /** @var Collection<int, IntegrationPlugin> $cached */
                return $cached;
            }

            if (! SchemaMetadataCache::hasTable('integration_plugins')) {
                $this->markDegraded();
                Log::warning('[定时任务] 插件表不可用，已跳过插件任务注册');

                return null;
            }

            $plugins = IntegrationPlugin::query()
                ->where('status', IntegrationPlugin::STATUS_ENABLED)
                ->orderBy('domain')
                ->orderBy('slug')
                ->get();

            $slot->offsetSet('plugins', $plugins);
            ScheduleHookService::markEnabledPluginsStored($slot);
        } catch (\Throwable $exception) {
            $this->markDegraded();
            Log::error('[定时任务] 查询已启用插件失败，已隔离插件任务注册', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return null;
        }

        return $plugins;
    }

    private function markDegraded(): void
    {
        $state = (array) config('idc.schedule_runtime.plugin_tasks', []);
        config(['idc.schedule_runtime.plugin_tasks' => [
            'status' => 'degraded',
            'error_count' => (int) ($state['error_count'] ?? 0) + 1,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pluginContext(PluginManifest $manifest, ?\Throwable $exception = null): array
    {
        $context = [
            'domain' => $manifest->domain,
            'slug' => $manifest->slug,
        ];

        if ($exception !== null) {
            $context['message'] = $exception->getMessage();
            $context['exception'] = $exception::class;
        }

        return $context;
    }
}
