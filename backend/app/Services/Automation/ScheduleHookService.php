<?php

namespace App\Services\Automation;

use App\Models\IntegrationPlugin;
use App\Services\Automation\Contracts\ScheduleHook;
use App\Services\Automation\Heartbeat\ScheduleDeclarationNormalizer;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Support\SchemaMetadataCache;
use ArrayObject;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ScheduleHookService
{
    public const HOOK_BEFORE_CRON = 'before_cron';

    public const HOOK_AFTER_CRON = 'after_cron';

    public const HOOK_BEFORE_DAILY_CRON = 'before_daily_cron';

    public const HOOK_AFTER_DAILY_CRON = 'after_daily_cron';

    public const HOOK_AFTER_FIVE_MINUTE_CRON = 'after_five_minute_cron';

    public const HOOK_AFTER_HALF_HOUR_MINUTE_CRON = 'after_half_hour_minute_cron';

    public const HOOK_TASK_BEFORE = 'task.before';

    public const HOOK_TASK_AFTER = 'task.after';

    public const HOOK_TASK_FAILED = 'task.failed';

    public const HOOK_EVERY_MINUTE = 'tick.every_minute';

    public const HOOK_EVERY_FIVE_MINUTES = 'tick.every_five_minutes';

    public const HOOK_HOURLY = 'tick.hourly';

    public const HOOK_DAILY = 'tick.daily';

    /**
     * 启用插件集合的作用域内共享缓存槽的容器键。
     *
     * 为什么不能逐 hook 重复查询：LegacyScheduleHookTaskProvider 会对 8 个固定 hook 各调用一次
     * hasListeners()，而 overview() 里「第三方任务来源标记」与「心跳任务注册表」又会各求值一次该
     * Provider，于是同一份「已启用插件集合」在单次总览请求中被重复扫描 16 次；插件定时任务 Provider
     * 再独立扫描 2 次。这些查询的入参完全相同、插件启停状态在单次请求内也不会变化，属于常量级重复。
     *
     * 作用域说明：本服务由容器自动解析（transient），同一次 overview() 会实例化两份，因此缓存不能放在
     * 实例属性上（否则重复次数只减半）；这里把槽登记进当前请求/Job 的 scoped 容器。框架只在队列 Job
     * 前后（QueueServiceProvider）与 Octane 请求边界调用 forgetScopedInstances()，传统 FPM 每请求独立
     * 进程无需清理；因此额外在槽内记录当前请求对象并按身份比较，跨请求或跨 Job 复用同一进程时自动失效，
     * 不依赖框架是否调用 forgetScopedInstances()。
     *
     * 常驻进程补充：纯 artisan 常驻进程没有新的 HTTP 请求，容器里的 'request' 始终是启动时由
     * SetRequestForConsole 绑定的那个 console Request，作用域比较永远相等，槽会跨心跳周期一直命中
     * 旧集合。因此槽内同时记录写入时间，并以 ENABLED_PLUGINS_CACHE_TTL_SECONDS 作为第二道失效边界。
     */
    public const ENABLED_PLUGINS_CACHE_KEY = 'idc.automation.enabled_integration_plugins';

    /**
     * 启用插件集合缓存槽的存活时间（秒）。
     *
     * 作用域比较只能识别「换了请求对象」这一种变化；纯 artisan 常驻进程里 request 恒等，必须靠时间兜底。
     * 插件启停是低频运维操作，最迟 TTL 秒后生效是可接受的滞后上界；代价只是常驻进程每个 TTL 周期
     * 最多多查一次插件清单，不影响单次请求内的常量级重复消除。
     */
    public const ENABLED_PLUGINS_CACHE_TTL_SECONDS = 60;

    public function __construct(
        private Container $container,
    ) {}

    /**
     * 取得当前作用域内共享的启用插件缓存槽。
     *
     * 槽内以 'plugins' 键存放查询成功的启用插件集合（Illuminate\Database\Eloquent\Collection）。
     * 只有成功结果才写入：表缺失与查询异常都不落槽，保证异常路径上的日志与降级次数与修复前一致。
     */
    public static function enabledPluginsSlot(Container $container): ArrayObject
    {
        if (! $container->bound(self::ENABLED_PLUGINS_CACHE_KEY)) {
            $container->scoped(
                self::ENABLED_PLUGINS_CACHE_KEY,
                static fn (): ArrayObject => new ArrayObject,
            );
        }

        $slot = $container->make(self::ENABLED_PLUGINS_CACHE_KEY);
        if (! $slot instanceof ArrayObject) {
            return new ArrayObject;
        }

        // scoped 实例只在 HTTP 响应结束、队列 Job 前后由框架清理；同一进程若连续处理多个请求
        // （HTTP 测试、常驻运行时），scoped 实例会跨请求存活。这里保存请求对象本身并按身份比较，
        // 一旦换了请求就丢弃上一轮的缓存，避免读到上一个请求的插件清单。
        $scope = self::currentRequestScope($container);
        $stale = $slot->offsetExists('scope') && $slot->offsetGet('scope') !== $scope;

        // 纯 artisan 常驻进程的 request 只绑定一次且永不替换，仅靠作用域比较无法失效：
        // 插件在别的进程被停用后，本进程会一直读到旧集合。写入时间超过 TTL 即视为过期；
        // 有集合却没有写入时间（不走 markEnabledPluginsStored() 的写法）同样按过期处理，避免永不过期。
        if (! $stale && $slot->offsetExists('plugins')) {
            $storedAt = $slot->offsetExists('stored_at') ? $slot->offsetGet('stored_at') : null;
            $stale = ! is_float($storedAt)
                || (microtime(true) - $storedAt) >= (float) self::ENABLED_PLUGINS_CACHE_TTL_SECONDS;
        }

        if ($stale) {
            $slot->exchangeArray([]);
        }
        $slot->offsetSet('scope', $scope);

        return $slot;
    }

    /**
     * 记录缓存集合的写入时刻，供 enabledPluginsSlot() 判断 TTL 是否过期。
     *
     * 只由真正写入 'plugins' 的两处调用：ScheduleHookService::enabledPlugins() 与
     * PluginScheduledTaskProvider::enabledPlugins()。失败路径不写时间戳，与「失败不落槽」一致。
     */
    public static function markEnabledPluginsStored(ArrayObject $slot): void
    {
        $slot->offsetSet('stored_at', microtime(true));
    }

    /**
     * 取得当前请求对象，用作缓存作用域标识；没有 request 绑定时统一返回 null。
     *
     * 这里持有请求对象本身而不是 spl_object_id()：object id 在对象被回收后会被 PHP 复用，
     * 长驻运行时中上一次请求的 id 可能恰好等于新请求的 id，缓存会被误判成同一作用域而命中旧数据。
     * 按对象身份比较不存在这个隐患，代价只是多存活一个请求对象，到下一次覆盖时即释放。
     */
    private static function currentRequestScope(Container $container): ?object
    {
        if (! $container->bound('request')) {
            return null;
        }

        return $container->make('request');
    }

    public function hasListeners(string $hook): bool
    {
        return $this->listenersFor($hook) !== [];
    }

    public function run(string $hook, array $context = []): array
    {
        $results = [];

        foreach ($this->listenersFor($hook) as $listener) {
            $listenerName = $this->describeListener($listener);

            try {
                $results[] = [
                    'listener' => $listenerName,
                    'status' => 'success',
                    'result' => $this->normalizeResult($this->invokeListener($listener, $hook, $context)),
                ];
            } catch (Throwable $exception) {
                Log::warning('[调度Hook] 执行失败', [
                    'hook' => $hook,
                    'listener' => $listenerName,
                    'task_key' => $context['task_key'] ?? null,
                    'task_name' => $context['task_name'] ?? null,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);

                $results[] = [
                    'listener' => $listenerName,
                    'status' => 'failed',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function runMany(array $hooks, array $context = []): array
    {
        $results = [];

        foreach (array_values(array_unique(array_filter($hooks))) as $hook) {
            $results[$hook] = $this->run((string) $hook, $context);
        }

        return $results;
    }

    private function listenersFor(string $hook): array
    {
        // 系统配置也支持单个 [ClassName::class, 'method'] 描述器；不能直接
        // array_merge，否则描述器会被拆成两个独立字符串监听器。
        $listeners = ScheduleDeclarationNormalizer::list($this->configuredListenersFor($hook));
        $pluginListeners = $this->pluginListenersFor($hook);

        return array_values(array_filter(array_merge(
            $listeners,
            $pluginListeners,
        )));
    }

    /**
     * Hook 名允许使用点号（例如 task.before），不能直接拼到 config() 的路径中，
     * 否则会被 Laravel 当作嵌套键而读不到 config/schedule_hooks.php 中的字面量键。
     * 同时兼容运行时用 config()->set() 写入嵌套路径的测试和诊断场景。
     *
     * @return array<mixed>
     */
    private function configuredListenersFor(string $hook): array
    {
        $configured = config('schedule_hooks.listeners', []);
        if (! is_array($configured)) {
            return [];
        }

        $literalListeners = $configured[$hook] ?? [];
        if (is_array($literalListeners) && $literalListeners !== []) {
            return $literalListeners;
        }

        $nestedListeners = config("schedule_hooks.listeners.{$hook}", []);
        if (is_array($nestedListeners) && $nestedListeners !== []) {
            return $nestedListeners;
        }

        return is_array($literalListeners) ? $literalListeners : [];
    }

    private function pluginListenersFor(string $hook): array
    {
        // 与原实现保持完全相同的执行顺序：先探测插件表，表缺失时静默返回，不再解析扫描器与加载器。
        // 这里沿用 SchemaMetadataCache::hasTable()，与下面 enabledPlugins() 内的探测共用同一份进程内
        // 表元数据缓存，所以这次「多探一次」不会产生任何 SQL。
        try {
            if (! SchemaMetadataCache::hasTable('integration_plugins')) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        try {
            $scanner = $this->container->make(PluginScanner::class);
            $fileLoader = $this->container->make(PluginFileLoader::class);
            $plugins = $this->enabledPlugins($hook);
        } catch (Throwable $exception) {
            Log::warning('[调度Hook] 查询已启用插件失败，已跳过插件监听器', [
                'hook' => $hook,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return [];
        }

        // enabledPlugins() 在「插件表不存在」与「查询抛异常」两条路径上都返回 null，
        // 语义都是「没有可用的插件监听器」。这里必须显式判空：漏掉会变成在 null 上调用 each()，
        // 把原本的静默降级放大成致命错误，进而让 runMany() 在调度执行主链路上直接抛出。
        if ($plugins === null) {
            return [];
        }

        $listeners = [];

        $plugins
            ->each(function (IntegrationPlugin $plugin) use ($hook, $scanner, $fileLoader, &$listeners): void {
                try {
                    $manifest = $scanner->find((string) $plugin->domain, (string) $plugin->slug);
                } catch (Throwable) {
                    return;
                }

                if ($manifest === null) {
                    return;
                }

                $hookMap = is_array($manifest->extra['schedule_hooks'] ?? null)
                    ? $manifest->extra['schedule_hooks']
                    : [];
                $definitions = $hookMap[$hook] ?? [];
                if ($definitions === []) {
                    return;
                }

                try {
                    $fileLoader->ensureLoaded($manifest);
                } catch (Throwable $exception) {
                    Log::warning('[调度Hook] 插件监听器文件加载失败', [
                        'domain' => $manifest->domain,
                        'slug' => $manifest->slug,
                        'hook' => $hook,
                        'message' => $exception->getMessage(),
                        'exception' => $exception::class,
                    ]);

                    return;
                }

                foreach (ScheduleDeclarationNormalizer::list($definitions) as $definition) {
                    $listeners[] = $definition;
                }
            });

        return $listeners;
    }

    /**
     * 读取已启用的插件集合，并在当前作用域内复用。
     *
     * 表存在性探测走 SchemaMetadataCache::hasTable()（进程内表元数据缓存），不再逐次直查
     * information_schema；启用插件集合写入 enabledPluginsSlot() 提供的 scoped 槽。
     * 表不存在时保持原有静默跳过的行为，查询异常时保持原有的 Log::warning 文案与字段。
     * 两类失败都不写缓存，因此后续调用仍会各自重试并各自记录日志，降级语义不变。
     *
     * @return Collection<int, IntegrationPlugin>|null
     */
    private function enabledPlugins(string $hook): ?Collection
    {
        $slot = self::enabledPluginsSlot($this->container);

        $cached = $slot->offsetExists('plugins') ? $slot->offsetGet('plugins') : null;
        if ($cached instanceof Collection) {
            /** @var Collection<int, IntegrationPlugin> $cached */
            return $cached;
        }

        try {
            if (! SchemaMetadataCache::hasTable('integration_plugins')) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        try {
            $plugins = IntegrationPlugin::query()
                ->where('status', IntegrationPlugin::STATUS_ENABLED)
                ->orderBy('domain')
                ->orderBy('slug')
                ->get();
        } catch (Throwable $exception) {
            Log::warning('[调度Hook] 查询已启用插件失败，已跳过插件监听器', [
                'hook' => $hook,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return null;
        }

        $slot->offsetSet('plugins', $plugins);
        self::markEnabledPluginsStored($slot);

        return $plugins;
    }

    private function invokeListener(mixed $listener, string $hook, array $context): mixed
    {
        if (is_string($listener)) {
            $instance = $this->container->make($listener);

            return $this->invokeInstance($instance, $hook, $context);
        }

        if (is_array($listener)) {
            if (isset($listener['class'])) {
                $instance = $this->container->make((string) $listener['class']);
                $method = (string) ($listener['method'] ?? 'handle');

                if (! is_callable([$instance, $method])) {
                    throw new InvalidArgumentException("调度Hook监听器缺少方法：{$method}");
                }

                return $instance->{$method}($hook, $context);
            }

            if (count($listener) === 2 && is_string($listener[0] ?? null) && is_string($listener[1] ?? null)) {
                $instance = $this->container->make($listener[0]);
                $method = $listener[1];

                if (! is_callable([$instance, $method])) {
                    throw new InvalidArgumentException("调度Hook监听器缺少方法：{$method}");
                }

                return $instance->{$method}($hook, $context);
            }

            if (is_callable($listener)) {
                return $listener($hook, $context);
            }
        }

        if (is_callable($listener)) {
            return $listener($hook, $context);
        }

        throw new InvalidArgumentException('调度Hook监听器配置无效');
    }

    private function invokeInstance(object $instance, string $hook, array $context): mixed
    {
        if ($instance instanceof ScheduleHook) {
            return $instance->handle($hook, $context);
        }

        if (is_callable($instance)) {
            return $instance($hook, $context);
        }

        if (is_callable([$instance, 'handle'])) {
            return $instance->handle($hook, $context);
        }

        throw new InvalidArgumentException('调度Hook监听器必须实现 handle() 或 __invoke()');
    }

    private function describeListener(mixed $listener): string
    {
        if (is_string($listener)) {
            return $listener;
        }

        if (is_array($listener)) {
            if (isset($listener['class'])) {
                return (string) $listener['class'].'@'.(string) ($listener['method'] ?? 'handle');
            }

            if (isset($listener[0], $listener[1]) && is_string($listener[0]) && is_string($listener[1])) {
                return $listener[0].'@'.$listener[1];
            }

            return 'array-listener';
        }

        return is_object($listener) ? $listener::class : gettype($listener);
    }

    private function normalizeResult(mixed $result): mixed
    {
        if ($result === null || is_scalar($result)) {
            return $result;
        }

        if (is_array($result)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeResult($item), $result);
        }

        if (is_object($result)) {
            return [
                'type' => 'object',
                'class' => $result::class,
            ];
        }

        return [
            'type' => gettype($result),
        ];
    }
}
