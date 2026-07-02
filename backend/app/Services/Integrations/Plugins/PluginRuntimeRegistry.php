<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Exceptions\BusinessException;
use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\Adapters\PluginMailDriver;
use App\Services\Integrations\Plugins\Adapters\PluginPaymentGateway;
use App\Services\Integrations\Plugins\Adapters\PluginSmsDriver;
use App\Services\Integrations\Plugins\Adapters\PluginUpstreamDriver;
use App\Services\Integrations\Plugins\Adapters\PluginVerificationDriver;
use App\Services\Mail\Contracts\MailDriver;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Verification\Contracts\VerificationDriver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PluginRuntimeRegistry
{
    public function __construct(
        private readonly Container $container,
        private readonly PluginScanner $scanner,
        private readonly PluginFileLoader $fileLoader,
        private readonly PluginConfigRepository $configRepository,
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

        if (! Schema::hasTable('integration_plugins')) {
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

        $manifest = $this->scanner->requireManifest((string) $plugin->domain, (string) $plugin->slug);
        $entry = $this->makeExecutableEntry($manifest);
        $request = [
            'domain' => $manifest->domain,
            'slug' => $manifest->slug,
            'key' => $manifest->key,
            'action' => $resolvedAction,
            'payload' => $payload,
            'config' => $this->configRepository->resolvedConfig($plugin),
            'context' => $context,
        ];

        try {
            $raw = $entry->execute($request);
        } catch (BusinessException $exception) {
            Log::warning('[plugins] business exception', [
                'domain' => $manifest->domain,
                'slug' => $manifest->slug,
                'action' => $resolvedAction,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('[plugins] execute failed', [
                'domain' => $manifest->domain,
                'slug' => $manifest->slug,
                'action' => $resolvedAction,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new BusinessException('插件执行失败', 42200);
        }

        $result = is_array($raw) ? $raw : ['data' => $raw];

        return array_merge([
            'success' => (bool) ($result['success'] ?? true),
            'action' => $resolvedAction,
            'plugin' => [
                'domain' => $manifest->domain,
                'slug' => $manifest->slug,
                'key' => $manifest->key,
                'name' => $manifest->name,
            ],
            'message' => (string) ($result['message'] ?? ''),
            'data' => $result['data'] ?? [],
            'raw' => $result['raw'] ?? [],
        ], $result);
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

        if (! Schema::hasTable('integration_plugins')) {
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

        $entries = [];
        $resolvedKeys = [];

        foreach ($plugins as $plugin) {
            if (! $plugin->isEnabled()) {
                continue;
            }

            $manifest = $this->scanner->find((string) $plugin->domain, (string) $plugin->slug);
            if (! $manifest instanceof PluginManifest) {
                Log::warning('[plugins] enabled plugin manifest missing', [
                    'domain' => $plugin->domain,
                    'slug' => $plugin->slug,
                ]);

                continue;
            }

            $instance = $this->makeAdapter($manifest, $contract);
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

    public function healthCheck(IntegrationPlugin $plugin): array
    {
        $manifest = $this->scanner->requireManifest((string) $plugin->domain, (string) $plugin->slug);
        $entry = $this->makeExecutableEntry($manifest);
        $result = [
            'healthy' => true,
            'message' => '插件加载正常',
            'entry_class' => $manifest->entryClass,
            'provider_class' => $manifest->providerClass,
        ];

        if (method_exists($entry, 'healthCheck')) {
            $raw = $entry->healthCheck();
            if (is_array($raw)) {
                $result = array_merge($result, $raw);
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

    private function makeAdapter(PluginManifest $manifest, string $contract): ?object
    {
        return match ($contract) {
            PaymentGatewayInterface::class => new PluginPaymentGateway($this, $manifest),
            VerificationDriver::class => new PluginVerificationDriver($this, $manifest),
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
