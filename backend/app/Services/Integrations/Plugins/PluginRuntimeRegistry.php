<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Exceptions\BusinessException;
use App\Models\IntegrationPlugin;
use App\Models\IntegrationPluginRuntimeLog;
use App\Services\Integrations\Plugins\Adapters\PluginMailDriver;
use App\Services\Integrations\Plugins\Adapters\PluginPaymentGateway;
use App\Services\Integrations\Plugins\Adapters\PluginSmsDriver;
use App\Services\Integrations\Plugins\Adapters\PluginUpstreamDriver;
use App\Services\Integrations\Plugins\Adapters\PluginVerificationDriver;
use App\Services\Integrations\Plugins\Support\PluginProbeMemo;
use App\Services\Mail\Contracts\MailDriver;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Verification\Contracts\VerificationDriver;
use App\Support\PayloadLimiter;
use App\Support\SchemaMetadataCache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PluginRuntimeRegistry
{
    /** 支付回调验签动作：未认证入口，验签失败不落 runtime log */
    private const VERIFY_NOTIFY_ACTION = 'payment.verify_notify';

    /**
     * 探测类动作：高频幂等，只服务列表/匹配判断（如结账页渲染一次可用网关列表，
     * 就会对每个网关各执行一次 is_enabled）。请求内 memo 短路 + 成功结果不落运行日志；
     * 失败照常落库保留诊断链路。
     */
    private const PROBE_ACTIONS = ['payment.is_enabled', 'payment.matches_merchant'];

    /** 运行日志 meta 存储上限：叶子字符串与整体编码分别限宽，防止上游大报文撑爆日志表 */
    private const RUNTIME_META_MAX_BYTES = 16384;

    private const RUNTIME_META_PREVIEW_BYTES = 4096;

    /**
     * 包摘要校验结果缓存，键为 `domain:slug:recorded_hash:enforce`。
     *
     * 插件类在常驻进程首次加载后即驻留内存，文件替换必须重启才可能生效，
     * 因此同一进程内重复计算全量摘要没有额外安全收益，只会把插件注册和
     * 每次插件动作都拖成目录遍历。键携带安装时记录的摘要值，重装或升级后自动失效。
     *
     * @var array<string, bool>
     */
    private static array $packageIntegrityCache = [];

    public function __construct(
        private readonly Container $container,
        private readonly PluginScanner $scanner,
        private readonly PluginFileLoader $fileLoader,
        private readonly PluginConfigRepository $configRepository,
        private readonly PluginPackageHasher $packageHasher,
        private readonly PluginCompatibility $compatibility,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(string $domain, string $slugOrKey, string $action, array $payload = [], array $context = []): array
    {
        $resolvedDomain = PluginDomain::assertValid($domain);
        $resolvedIdentifier = trim($slugOrKey);
        $resolvedAction = trim($action);

        if ($resolvedIdentifier === '' || $resolvedAction === '') {
            throw new BusinessException('插件或执行动作不能为空', 42200);
        }

        if (! SchemaMetadataCache::hasTable('integration_plugins')) {
            throw new BusinessException('插件系统尚未初始化', 42200);
        }

        $plugin = IntegrationPlugin::query()
            ->where('domain', $resolvedDomain)
            ->where(static function ($query) use ($resolvedIdentifier): void {
                $query->where('slug', $resolvedIdentifier)
                    ->orWhere('plugin_key', $resolvedIdentifier);
            })
            ->first();

        if (! $plugin instanceof IntegrationPlugin || ! $plugin->isEnabled()) {
            throw new BusinessException('插件未安装或未启用', 42200);
        }

        if (in_array($resolvedAction, self::PROBE_ACTIONS, true)) {
            $memo = $this->container->make(PluginProbeMemo::class);
            $memoKey = $this->probeMemoKey($resolvedDomain, $resolvedIdentifier, $resolvedAction, $payload);
            $cached = $memo->get($memoKey);
            if ($cached !== null) {
                return $cached;
            }

            $response = $this->executePlugin($plugin, $resolvedAction, $payload, $context);
            if (($response['success'] ?? false) === true) {
                $memo->put($memoKey, $response);
            }

            return $response;
        }

        return $this->executePlugin($plugin, $resolvedAction, $payload, $context);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function probeMemoKey(string $domain, string $identifier, string $action, array $payload): string
    {
        try {
            $payloadHash = md5((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            $payloadHash = md5(serialize($payload));
        }

        return implode('|', [$domain, $identifier, $action, $payloadHash]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function executePlugin(IntegrationPlugin $plugin, string $resolvedAction, array $payload = [], array $context = []): array
    {
        $manifest = $this->scanner->requireManifest((string) $plugin->domain, (string) $plugin->slug);

        // resolveEntries 只在启动期过滤；动作执行是另一条独立加载路径，必须再校验一次。
        if (! $this->assertPackageIntegrity($plugin, $manifest)) {
            throw new BusinessException('插件包内容与安装记录不一致，已阻止执行，请联系管理员', 42200);
        }

        // 宿主版本区间同样要二次校验：跳过注册表不等于外部无法按 slug 直接调用。
        $this->compatibility->assertCompatible($manifest);

        $entry = $this->makeExecutableEntry($manifest);
        $startedAt = microtime(true);
        $traceId = $this->resolveTraceId($context);
        $request = [
            'domain' => $manifest->domain,
            'slug' => $manifest->slug,
            'key' => $manifest->key,
            'action' => $resolvedAction,
            'payload' => $payload,
            'config' => $this->configRepository->resolvedConfig($plugin),
            'context' => array_merge($context, ['trace_id' => $traceId]),
        ];

        try {
            $raw = $entry->execute($request);
            $result = is_array($raw) ? $raw : ['data' => $raw];
            // 插件自定义字段先并入，随后由平台统一覆盖归一化字段。
            // 顺序不能反：array_merge 会让插件返回的原始值盖掉类型转换结果，
            // 例如字符串 'false' 会被下游 `=== false` 判定为成功。
            $response = array_merge($result, [
                'success' => $this->normalizeSuccess($result),
                'action' => $resolvedAction,
                'plugin' => [
                    'domain' => $manifest->domain,
                    'slug' => $manifest->slug,
                    'key' => $manifest->key,
                    'name' => $manifest->name,
                ],
                'message' => $this->normalizeMessage($result['message'] ?? null),
                'data' => $result['data'] ?? [],
                'raw' => $result['raw'] ?? [],
            ]);

            $this->recordRuntimeLog($plugin, $manifest, $resolvedAction, $payload, $context, $response, 'success', $startedAt, $traceId);

            return $response;
        } catch (BusinessException $exception) {
            $this->recordRuntimeLog($plugin, $manifest, $resolvedAction, $payload, $context, null, 'failed', $startedAt, $traceId, $exception);
            Log::warning('[plugins] business exception', [
                'domain' => $manifest->domain,
                'slug' => $manifest->slug,
                'action' => $resolvedAction,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (\Throwable $exception) {
            $this->recordRuntimeLog($plugin, $manifest, $resolvedAction, $payload, $context, null, 'failed', $startedAt, $traceId, $exception);
            Log::error('[plugins] execute failed', [
                'domain' => $manifest->domain,
                'slug' => $manifest->slug,
                'action' => $resolvedAction,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new BusinessException('插件执行失败', 42200);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $response
     */
    private function recordRuntimeLog(
        IntegrationPlugin $plugin,
        PluginManifest $manifest,
        string $action,
        array $payload,
        array $context,
        ?array $response,
        string $status,
        float $startedAt,
        string $traceId,
        ?\Throwable $exception = null,
    ): void {
        if (! SchemaMetadataCache::hasTable('integration_plugin_runtime_logs')) {
            return;
        }

        if ($this->shouldSkipRuntimeLog($action, $response)) {
            return;
        }

        // 运行日志按项目规则保留完整信息、不做脱敏（AGENTS.md：不在管理员端做脱敏，
        // 不在日志做脱敏，都需要显示完整信息）；PayloadLimiter 只限量、不脱敏，
        // 因此 payload/context 中出现 api_key、web_session_cookie 等敏感字段属预期行为
        // （如 AdminConfigurationV2QueryService::runSupplierTask 传入的绑定投影）。
        // 调用方若不想让某字段入日志，应在传入前自行裁剪；如需改变该语义须先取得项目裁决。
        try {
            IntegrationPluginRuntimeLog::query()->create([
                'trace_id' => $traceId !== '' ? $traceId : null,
                'domain' => $manifest->domain,
                'plugin_id' => (int) $plugin->id,
                'plugin_key' => $manifest->key,
                'slug' => $manifest->slug,
                'action' => $action,
                'binding_id' => $this->positiveInt($context['binding_id'] ?? null),
                'bindable_type' => $this->nullableString($context['bindable_type'] ?? null, 120),
                'bindable_id' => $this->positiveInt($context['bindable_id'] ?? null),
                'actor_type' => $this->nullableString($context['actor_type'] ?? null, 50),
                'actor_id' => $this->positiveInt($context['actor_id'] ?? null),
                'status' => $status,
                'duration_ms' => (int) max(0, round((microtime(true) - $startedAt) * 1000)),
                'error_code' => $exception === null ? null : $this->nullableString((string) $exception->getCode(), 80),
                'error_message' => $exception === null ? null : $this->nullableString($exception->getMessage(), 500),
                'request_meta_json' => $this->runtimeMeta([
                    'payload' => $payload,
                    'context' => $context,
                ]),
                'response_meta_json' => $response === null ? null : $this->runtimeMeta($response),
                'created_at' => now(),
            ]);
        } catch (\Throwable $logException) {
            Log::warning('[plugins] runtime log write failed', [
                'domain' => $manifest->domain,
                'slug' => $manifest->slug,
                'action' => $action,
                'message' => $logException->getMessage(),
            ]);
        }
    }

    /**
     * 支付回调验签是未认证入口，任何人都能高频触发。验签未通过时不落 runtime log，
     * 避免被利用做磁盘/表膨胀；验签通过（真实回调）仍然完整落库，保留支付审计链路。
     * 验签失败本身已由 VerifyPaymentCallbackSignature / VerifyAlipayCallbackSignature
     * 中间件以脱敏方式写入应用日志，审计不丢失。
     *
     * @param  array<string, mixed>|null  $response
     */
    private function shouldSkipRuntimeLog(string $action, ?array $response): bool
    {
        // 探测类动作的成功结果是纯噪音（每次列表渲染每个网关一条），不落运行日志；
        // 失败仍完整落库。memo 命中的短路发生在 execute() 入口，本就不会走到这里。
        if (in_array($action, self::PROBE_ACTIONS, true) && ($response['success'] ?? false) === true) {
            return true;
        }

        if ($action !== self::VERIFY_NOTIFY_ACTION) {
            return false;
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        return ! (bool) ($data['verified'] ?? false);
    }

    /**
     * 归一化插件返回的 success。平台下游用严格 `=== false` 判失败，
     * 因此这里必须产出真正的 bool，否则失败会被当成功放行。
     *
     * 字符串按 Laravel `$request->boolean()` 的同一套语义处理：
     * 'false'、'0'、'off'、'no'、'' 视为失败。直接 `(bool)` 强转的话
     * 字符串 'false' 会变成 true，正是要防的那种情况。
     * 缺省视为成功；非标量属于契约违规，失败关闭。
     *
     * @param  array<string, mixed>  $result
     */
    private function normalizeSuccess(array $result): bool
    {
        if (! array_key_exists('success', $result)) {
            return true;
        }

        $success = $result['success'];

        if (is_bool($success)) {
            return $success;
        }

        if (is_string($success)) {
            return filter_var(trim($success), FILTER_VALIDATE_BOOLEAN);
        }

        if (is_int($success) || is_float($success)) {
            return (bool) $success;
        }

        return false;
    }

    /**
     * 插件返回的 message 约定是字符串；数组或对象直接强转会触发 PHP 警告，
     * 这里统一降级为空串，避免坏插件把 runtime log 写坏。
     */
    private function normalizeMessage(mixed $message): string
    {
        if (is_string($message)) {
            return $message;
        }

        if (is_scalar($message)) {
            return (string) $message;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveTraceId(array $context): string
    {
        $traceId = trim((string) ($context['trace_id'] ?? ''));

        return $traceId !== ''
            ? substr($traceId, 0, 64)
            : substr('plugin:'.str_replace('-', '', (string) Str::uuid()), 0, 64);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runtimeMeta(array $payload): array
    {
        // 对象/资源在 json_encode 时会被静默丢弃或产生无效结构（如 resolve_capability 返回的能力对象），
        // 先递归归一为可序列化的类快照，保证 runtime log 恒为可读 JSON。
        $jsonSafe = [];
        foreach ($payload as $key => $value) {
            $jsonSafe[$key] = $this->jsonSafeValue($value, new \WeakMap, 0);
        }

        // 截断超大 meta：异常上游报文曾把单行 runtime log 撑到兆级（表均行宽 1.6KB 的主因之一）。
        return PayloadLimiter::limit(
            $jsonSafe,
            PayloadLimiter::DEFAULT_LEAF_MAX_BYTES,
            self::RUNTIME_META_MAX_BYTES,
            self::RUNTIME_META_PREVIEW_BYTES,
        );
    }

    /** 递归归一的最大对象/数组嵌套深度，超出后截断，防止循环引用或超深结构打崩 worker。 */
    private const JSON_SAFE_MAX_DEPTH = 10;

    private function jsonSafeValue(mixed $value, \WeakMap $seen, int $depth): mixed
    {
        if ($value instanceof \Closure) {
            return null;
        }

        if (is_object($value)) {
            if ($depth >= self::JSON_SAFE_MAX_DEPTH) {
                return ['__class' => $value::class, '__truncated' => '(max depth)'];
            }

            if (isset($seen[$value])) {
                return ['__class' => $value::class, '__circular' => true];
            }

            $seen[$value] = true;

            $properties = [];

            foreach (get_object_vars($value) as $key => $property) {
                $properties[$key] = $this->jsonSafeValue($property, $seen, $depth + 1);
            }

            return [
                '__class' => $value::class,
                'properties' => $properties,
            ];
        }

        if (is_resource($value)) {
            return null;
        }

        if (is_array($value)) {
            if ($depth >= self::JSON_SAFE_MAX_DEPTH) {
                return '(max depth)';
            }

            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->jsonSafeValue($item, $seen, $depth + 1);
            }

            return $normalized;
        }

        return $value;
    }

    private function positiveInt(mixed $value): ?int
    {
        $int = (int) ($value ?? 0);

        return $int > 0 ? $int : null;
    }

    private function nullableString(mixed $value, int $limit): ?string
    {
        $string = trim((string) ($value ?? ''));
        if ($string === '') {
            return null;
        }

        return mb_substr($string, 0, $limit);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return array<int, T>
     */
    public function resolveEntries(string $domain, string $contract): array
    {
        $resolvedDomain = PluginDomain::assertValid($domain);

        if (! SchemaMetadataCache::hasTable('integration_plugins')) {
            return [];
        }

        $plugins = IntegrationPlugin::query()
            ->where('domain', $resolvedDomain)
            ->orderByDesc('status')
            ->orderBy('name')
            ->get();

        if ($plugins->isEmpty()) {
            return [];
        }

        // 单次解析只读一次宿主版本，避免逐个插件重复查询安装表。
        $hostCoreVersion = $this->compatibility->hostCoreVersion();
        $entries = [];
        $resolvedKeys = [];

        foreach ($plugins as $plugin) {
            if (! $plugin->isEnabled()) {
                continue;
            }

            $manifest = $this->scanner->find((string) $plugin->domain, (string) $plugin->slug);
            if (! $manifest instanceof PluginManifest) {
                Log::error('[plugins] enabled plugin manifest missing — plugin is enabled but files not found', [
                    'domain' => $plugin->domain,
                    'slug' => $plugin->slug,
                    'plugin_id' => $plugin->id,
                ]);

                continue;
            }

            if (! $this->assertPackageIntegrity($plugin, $manifest)) {
                continue;
            }

            if (! $this->assertRuntimeCompatible($plugin, $manifest, $hostCoreVersion)) {
                continue;
            }

            $instance = $this->makeAdapter($manifest, $contract, (int) $plugin->id);
            if ($instance !== null) {
                $entryKey = $this->resolveEntryKey($instance);
                if ($entryKey !== null && isset($resolvedKeys[$entryKey])) {
                    Log::warning('[plugins] duplicate enabled plugin key skipped', [
                        'domain' => $manifest->domain,
                        'slug' => $manifest->slug,
                        'entry_key' => $entryKey,
                    ]);

                    continue;
                }

                $entries[] = $instance;
                if ($entryKey !== null) {
                    $resolvedKeys[$entryKey] = true;
                }
            }
        }

        return $entries;
    }

    /**
     * 校验插件声明的宿主环境要求；不满足时跳过加载并记 error，保留注册记录供人工处置。
     *
     * @param  IntegrationPlugin  $plugin  已启用插件注册记录
     * @param  PluginManifest  $manifest  当前磁盘清单
     * @param  string|null  $hostCoreVersion  宿主程序版本，单次解析内复用
     * @return bool 是否允许进入运行时注册表
     */
    private function assertRuntimeCompatible(IntegrationPlugin $plugin, PluginManifest $manifest, ?string $hostCoreVersion): bool
    {
        $problems = $this->compatibility->violations($manifest->requires, $hostCoreVersion);
        if ($problems === []) {
            return true;
        }

        Log::error('[plugins] 插件与当前宿主环境不兼容：已跳过运行时加载', [
            'domain' => $plugin->domain,
            'slug' => $plugin->slug,
            'plugin_id' => $plugin->id,
            'requires' => $manifest->requires,
            'problems' => $problems,
        ]);

        return false;
    }

    /**
     * 校验插件包内容摘要与安装时记录一致，防止审核后替换文件。
     *
     * 三条件缺一不可：已记录 source_hash、当前目录摘要一致、摘要未被改动。
     * 存量插件在回填命令执行前 source_hash 为空，此时放行并记 error，
     * 不用「未知」状态阻断已在运行的能力；开关 plugin_integrity_enforce 为 true 后，
     * 摘要不一致会直接跳过该插件（不删注册记录、不自动禁用，保留人工处置入口）。
     *
     * @param  IntegrationPlugin  $plugin  已启用插件注册记录
     * @param  PluginManifest  $manifest  当前磁盘清单
     * @return bool 是否允许进入运行时注册表
     */
    private function assertPackageIntegrity(IntegrationPlugin $plugin, PluginManifest $manifest): bool
    {
        $recorded = trim((string) ($plugin->source_hash ?? ''));
        if ($recorded === '') {
            Log::error('[plugins] 插件包摘要缺失：插件缺少安装时记录的 source_hash', [
                'domain' => $plugin->domain,
                'slug' => $plugin->slug,
                'plugin_id' => $plugin->id,
                'suggestion' => 'php artisan plugins:backfill-source-hash --execute',
            ]);

            return true;
        }

        $enforced = $this->integrityEnforced();
        $cacheKey = $plugin->domain.':'.$plugin->slug.':'.$recorded.':'.($enforced ? '1' : '0');
        if (array_key_exists($cacheKey, self::$packageIntegrityCache)) {
            return self::$packageIntegrityCache[$cacheKey];
        }

        $allowed = $this->evaluatePackageIntegrity($plugin, $manifest, $recorded);
        self::$packageIntegrityCache[$cacheKey] = $allowed;

        return $allowed;
    }

    /**
     * 实际比对包摘要；结果由 {@see self::assertPackageIntegrity()} 缓存。
     *
     * @param  IntegrationPlugin  $plugin  已启用插件注册记录
     * @param  PluginManifest  $manifest  当前磁盘清单
     * @param  string  $recorded  安装时记录的包摘要
     * @return bool 是否允许加载或执行
     */
    private function evaluatePackageIntegrity(IntegrationPlugin $plugin, PluginManifest $manifest, string $recorded): bool
    {
        try {
            $current = $this->packageHasher->packageHash($manifest->basePath);
        } catch (\Throwable $exception) {
            Log::error('[plugins] 插件包摘要计算失败：插件包不可读或含禁止路径', [
                'domain' => $plugin->domain,
                'slug' => $plugin->slug,
                'plugin_id' => $plugin->id,
                'message' => $exception->getMessage(),
            ]);

            return ! $this->integrityEnforced();
        }

        if (hash_equals($recorded, $current)) {
            return true;
        }

        Log::error('[plugins] 插件包内容与安装时记录不一致：疑似审核后替换源码', [
            'domain' => $plugin->domain,
            'slug' => $plugin->slug,
            'plugin_id' => $plugin->id,
            'recorded_hash' => $recorded,
            'current_hash' => $current,
            'enforced' => $this->integrityEnforced(),
        ]);

        return ! $this->integrityEnforced();
    }

    /**
     * 是否强制阻断完整性不一致的插件；默认观察模式，仅记日志不阻断。
     */
    private function integrityEnforced(): bool
    {
        return (bool) config('integrations.plugin_integrity_enforce', false);
    }

    public function healthCheck(IntegrationPlugin $plugin): array
    {
        $manifest = $this->scanner->requireManifest((string) $plugin->domain, (string) $plugin->slug);
        $entry = $this->makeExecutableEntry($manifest);

        $result = [
            'healthy' => true,
            'message' => '插件加载正常',
            'entry_class' => $manifest->entryClass,
            'provider_class' => $manifest->providerClass,
            'details' => [],
        ];

        if (method_exists($entry, 'healthCheck')) {
            $raw = $entry->healthCheck();
            if (is_array($raw)) {
                if (isset($raw['healthy'])) {
                    $result['healthy'] = (bool) $raw['healthy'];
                }
                if (isset($raw['message']) && is_string($raw['message'])) {
                    $result['message'] = $raw['message'];
                }
                // 插件额外信息统一放入 details，不污染顶层结构
                $result['details'] = isset($raw['details']) && is_array($raw['details'])
                    ? $raw['details']
                    : array_diff_key($raw, array_flip(['healthy', 'message']));
            }
        }

        return $result;
    }

    private function makeExecutableEntry(PluginManifest $manifest): object
    {
        $this->fileLoader->ensureLoaded($manifest);
        $entry = $this->container->make($manifest->entryClass);

        if (! method_exists($entry, 'execute')) {
            throw new BusinessException('插件入口类缺少 execute 方法', 42200);
        }

        return $entry;
    }

    /**
     * 探测网关插件是否声明支持给定动作（不实际执行插件逻辑）。
     *
     * 插件入口类通过 supportsAction(string): bool 自报能力；尚未升级、
     * 缺少该方法的旧插件按不支持处理，调用方可据此决定可选动作的降级策略。
     */
    public function probeGatewaySupportsAction(PluginManifest $manifest, string $action): bool
    {
        return $this->probeGatewayActionDeclaration($manifest, $action) === true;
    }

    /**
     * 三态探测插件对某动作的能力声明：
     * - true：入口类已升级且声明支持；
     * - false：入口类已升级且声明不支持（可选动作可据此安全跳过执行）；
     * - null：旧插件未升级、无 supportsAction 方法（调用方须按「可能实现」处理）。
     *
     * 三态是必要的：只用 bool 无法区分「旧插件没说」与「新插件明确说不」，
     * 会导致要么旧插件功能丢失、要么新插件每次都被无谓执行。
     */
    public function probeGatewayActionDeclaration(PluginManifest $manifest, string $action): ?bool
    {
        $resolvedAction = trim($action);

        if ($resolvedAction === '') {
            return false;
        }

        $entry = $this->makeExecutableEntry($manifest);

        if (! method_exists($entry, 'supportsAction')) {
            return null;
        }

        return (bool) $entry->supportsAction($resolvedAction);
    }

    private function makeAdapter(PluginManifest $manifest, string $contract, ?int $pluginId = null): ?object
    {
        return match ($contract) {
            PaymentGatewayInterface::class => new PluginPaymentGateway($this, $manifest, $pluginId),
            VerificationDriver::class => new PluginVerificationDriver($this, $manifest, $pluginId),
            SmsDriver::class => new PluginSmsDriver($this, $manifest),
            MailDriver::class => new PluginMailDriver($this, $manifest),
            UpstreamDriver::class => new PluginUpstreamDriver($this, $manifest),
            default => null,
        };
    }

    private function resolveEntryKey(object $entry): ?string
    {
        if (! method_exists($entry, 'key')) {
            return null;
        }

        $key = trim((string) $entry->key());

        return $key !== '' ? $key : null;
    }
}
