# Demo：魔方财务上游插件

当前实现路径：

```text
backend/plugins/servers/mofang_finance/
├── MofangFinancePlugin.php
├── config.php
├── logic/
│   └── MofangFinance.php
└── lib/
    ├── MofangAuthManager.php
    ├── MofangCatalogService.php
    ├── MofangCloudConfigTemplate.php
    ├── MofangConsoleService.php
    ├── MofangFinanceAdapter.php
    ├── MofangFinanceDriver.php
    ├── MofangFinanceTransport.php
    ├── MofangNetworkService.php
    ├── MofangProductTypeMapper.php
    ├── MofangProvisionService.php
    ├── MofangRenewService.php
    ├── MofangSecurityService.php
    └── MofangStatusService.php
```

## 适用场景

上游插件用于产品拉取、开通、续费、状态同步和控制台操作。首批只支持魔方财务，provider key 必须保持 `mofang_finance_api`，不能别名成 `hosting_panel_api`。

## config.php demo

```php
<?php

declare(strict_types=1);

use App\Services\Upstream\Contracts\ProvidesConsoleAccess;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\ProvidesConsoleNetwork;
use App\Services\Upstream\Contracts\ProvidesConsoleRuntime;
use App\Services\Upstream\Contracts\ProvidesConsoleSecurity;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\Contracts\ProvidesRenewal;
use App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh;
use App\Services\Upstream\Contracts\ProvidesStatusSync;
use Caiwu\Plugins\Servers\MofangFinance\MofangFinancePlugin;

return [
    'info' => [
        'domain' => 'upstream',
        'slug' => 'mofang_finance',
        'key' => 'mofang_finance_api',
        'name' => '魔方财务接口',
        'version' => '1.0.0',
        'entry' => MofangFinancePlugin::class,
        'capabilities' => [
            ProvidesConsoleAccess::class,
            ProvidesConsoleCatalog::class,
            ProvidesConsoleNetwork::class,
            ProvidesConsoleRuntime::class,
            ProvidesConsoleSecurity::class,
            ProvidesProvisioning::class,
            ProvidesRenewal::class,
            ProvidesScheduledAuthRefresh::class,
            ProvidesStatusSync::class,
        ],
    ],
    'config' => [
        'mofang_notice' => [
            'title' => '配置说明',
            'type' => 'notice',
            'theme' => 'info',
            'content' => '该插件承载魔方财务上游差异适配，接口地址、账号和密钥由供应商配置维护。',
        ],
        'provider_key' => [
            'title' => '上游标识',
            'type' => 'readonly',
            'value' => 'mofang_finance_api',
            'description' => '供应商 interface_type 必须保持该值，不要别名为 hosting_panel_api。',
        ],
    ],
];
```

当前魔方财务上游配置仍来自供应商配置，不在插件配置中重复录入 API 地址、账号和密钥。插件负责把魔方财务能力注册到平台上游运行时，并在插件内部封装魔方财务协议动作。

## 入口类 demo

```php
<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\MofangFinance;

use Caiwu\Plugins\Servers\MofangFinance\Logic\MofangFinance;

class MofangFinancePlugin extends MofangFinance {}
```

## 业务类 demo

```php
<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\MofangFinance\Logic;

use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangFinanceDriver;

class MofangFinance
{
    public function __construct(
        private readonly MofangFinanceDriver $driver,
    ) {}

    public function key(): string
    {
        return $this->driver->key();
    }

    public function label(): string
    {
        return $this->driver->label();
    }

    public function capabilities(): array
    {
        return $this->driver->capabilities();
    }

    public function supports(string $capability): bool
    {
        return $this->driver->supports($capability);
    }

    public function resolve(string $capability): ?object
    {
        return $this->driver->resolve($capability);
    }

    public function execute(array $request): array
    {
        $action = trim((string) ($request['action'] ?? ''));
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];

        return match ($action) {
            'server.metadata' => [
                'success' => true,
                'action' => $action,
                'data' => [
                    'key' => $this->key(),
                    'label' => $this->label(),
                    'capabilities' => $this->capabilities(),
                ],
            ],
            'server.supports' => [
                'success' => true,
                'action' => $action,
                'data' => [
                    'supported' => $this->supports((string) ($payload['capability'] ?? '')),
                ],
            ],
            'server.resolve_capability' => [
                'success' => true,
                'action' => $action,
                'data' => [
                    'resolved' => $this->resolve((string) ($payload['capability'] ?? '')),
                ],
            ],
            default => [
                'success' => false,
                'action' => $action,
                'message' => 'Unsupported plugin action',
                'data' => [],
            ],
        };
    }
}
```

## 上游边界

插件负责：

- 声明 `domain=upstream`、`slug=mofang_finance`、`key=mofang_finance_api`。
- 在 `backend/plugins/servers/mofang_finance/lib/` 内提供 `MofangFinanceDriver`、`MofangFinanceAdapter` 和各能力 service。
- 处理魔方财务登录、JWT 缓存、刷新、401 自动重登。
- 归一化商品、价格、库存、配置项、开通、续费和状态同步结果。
- 执行 VNC、电源、重装、重置密码、NAT、安全组、监控、流量包、升降级等上游动作。
- 只返回平台允许消费的字段，不直接修改订单、账单、支付、余额或服务实例核心状态。

平台负责：

- 供应商配置管理。
- 商品绑定。
- 订单、账单、服务实例生命周期。
- 开通幂等、失败重试。
- 续费履约。
- 用户服务归属、控制台限流、权限、审计和操作日志。

`MofangFinanceAdapter` 必须显式声明业务 Service 会调用的方法；不要新增或依赖 `MofangFinanceAdapter::__call()` 这类动态转发入口。需要扩展新动作时，优先新增插件内部 service，再在 adapter 上补明确方法和测试。

## 测试要点

- `mofang_finance_api` 不能被归一化或别名为 `hosting_panel_api`。
- 启用插件后，上游 registry 能解析魔方财务能力。
- 商品拉取、开通、续费、状态同步、VNC 和控制台动作都通过插件能力入口执行，上层平台服务仍负责本地状态机和权限边界。

推荐命令：

```bash
cd backend
php artisan test tests/Feature/PluginRuntimeRegistryIntegrationTest.php tests/Feature/SupplierInterfaceTypeAliasRegressionTest.php tests/Unit/MofangServiceProviderTest.php tests/Unit/MofangConsoleAndNetworkServiceTest.php
```
