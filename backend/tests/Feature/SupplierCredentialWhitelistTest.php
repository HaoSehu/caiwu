<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Resources\Admin\V2\AdminSupplierResource;
use App\Models\Supplier;
use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\Integrations\Plugins\SupplierPluginCardRenderer;
use App\Services\Upstream\Contracts\ProvidesSupplierFormSchema;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

/**
 * 供应商凭据白名单回归（B5：V7-04 零覆盖缺口，BACKEND §9「敏感配置读取/编辑不泄露原值」
 * 与「供应商列表、详情、创建、更新响应不包含 api_key 原值」）：
 * 1. api_key / secret 原值不得出现在任何输出分支（含 card、upstream_binding）；
 * 2. 非敏感字段（region）照常输出，凭据仅以布尔标记存在；
 * 3. api_key 既不进 provider_config 白名单、也不进值存在性标记；
 * 4. 经构造注入的依赖在资源层显式使用（B6 后不再经 app() 定位）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class SupplierCredentialWhitelistTest extends TestCase
{
    use DatabaseTransactions;

    public function test_supplier_payload_never_returns_credential_raw_values(): void
    {
        $rawApiKey = 'RAW-API-KEY-'.uniqid();
        $rawSecret = 'RAW-SECRET-'.uniqid();

        $supplier = new Supplier([
            'name' => '测试供应商',
            'code' => 'test-supplier',
            'contact_name' => '张三',
            'contact_phone' => '13800000000',
            'contact_email' => 'supplier@example.test',
            'status' => 1,
            'sort_order' => 0,
        ]);

        $binding = [
            'id' => 9,
            'plugin_id' => 3,
            'provider_key' => 'test-provider',
            'environment' => 'production',
            'status' => 1,
            'priority' => 0,
            'base_url' => 'https://api.example.test',
            'has_base_url' => true,
            'account_name' => 'acct',
            'has_api_key' => true,
            'provider_config' => [
                'api_url' => 'https://api.example.test',
                'api_username' => 'admin',
                'api_key' => $rawApiKey,
                'secret_key' => $rawSecret,
                'region' => 'cn-east-1',
            ],
            'has_secret_values' => ['api_key' => true, 'secret_key' => true],
        ];

        $bindingResolver = Mockery::mock(PluginBindingResolver::class);
        $bindingResolver->shouldReceive('supplierBindingProjection')->andReturn($binding);

        // ProviderRegistry 为 final：以真实注册表 + 匿名驱动构造描述符，避免 mock 不可替换类。
        $driver = new class implements ProvidesSupplierFormSchema, UpstreamDriver
        {
            public function key(): string
            {
                return 'test-provider';
            }

            public function label(): string
            {
                return '测试上游';
            }

            public function capabilities(): array
            {
                return [];
            }

            public function supports(string $capability): bool
            {
                return false;
            }

            public function resolve(string $capability): ?object
            {
                return null;
            }

            public function supplierFormSchema(): array
            {
                return [
                    'fields' => [
                        ['key' => 'api_url'],
                        ['key' => 'api_username'],
                        ['key' => 'api_key', 'secret' => true],
                        ['key' => 'secret_key', 'secret' => true],
                        ['key' => 'region'],
                    ],
                ];
            }
        };
        $providerRegistry = new ProviderRegistry([$driver]);

        $cardRenderer = Mockery::mock(SupplierPluginCardRenderer::class);
        $cardRenderer->shouldReceive('render')->andReturn(['title' => '测试卡']);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(static fn () => null);

        $payload = AdminSupplierResource::make(
            $supplier,
            $bindingResolver,
            $providerRegistry,
            $cardRenderer,
        )->resolve($request);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);
        foreach ([$rawApiKey, $rawSecret] as $needle) {
            $this->assertStringNotContainsString($needle, $json, '供应商响应不得包含凭据原值');
        }

        // 非敏感配置照常输出；api_key 与全部 secret 字段不得进入白名单
        $this->assertSame('cn-east-1', $payload['provider_config']['region']);
        $this->assertArrayNotHasKey('api_key', $payload['provider_config']);
        $this->assertArrayNotHasKey('secret_key', $payload['provider_config']);
        $this->assertArrayNotHasKey('api_url', $payload['provider_config']);
        $this->assertArrayNotHasKey('api_username', $payload['provider_config']);

        // 凭据只以布尔标记表达存在性；api_key 连标记都不出现
        $this->assertTrue($payload['credentials']['api_credential_configured']);
        $this->assertSame(['secret_key' => true], $payload['credentials']['provider_values_configured']);

        $this->assertSame('测试上游', $payload['provider_label']);
        $this->assertSame('测试卡', $payload['card']['title']);

        $bindingCredentials = (array) ($payload['upstream_binding']['credentials_configured'] ?? []);
        $this->assertArrayNotHasKey('api_key', $bindingCredentials);
        $this->assertArrayHasKey('secret_key', $bindingCredentials);
    }
}
