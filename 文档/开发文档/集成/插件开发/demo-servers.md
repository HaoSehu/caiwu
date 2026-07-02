# Demo：上游服务插件

当前实现路径：

```text
backend/plugins/servers/demo_servers/
├── DemoServersPlugin.php
├── config.php
├── README.md
├── DEVELOPMENT.md
└── logic/
    └── DemoServers.php
```

## 适用场景

`demo_servers` 是 servers/upstream 功能域的演示插件，用于验证后台插件扫描、安装、启用、配置、运行时解析和 capability 调用链路。它只返回模拟数据，不请求真实上游，也不修改订单、账单、支付、余额或服务实例核心状态。

真实生产上游仍应使用真实 provider key，例如魔方财务使用 `mofang_finance_api`。`demo_servers` 只用于开发、测试和文档示例。

## config.php 要点

```php
return [
    'info' => [
        'domain' => 'upstream',
        'slug' => 'demo_servers',
        'key' => 'demo_servers',
        'name' => 'Demo 上游服务',
        'entry' => DemoServersPlugin::class,
        'capabilities' => [
            ProvidesConsoleCatalog::class,
            ProvidesProvisioning::class,
            ProvidesRenewal::class,
            ProvidesStatusSync::class,
            ProvidesConsoleRuntime::class,
            ProvidesConsoleAccess::class,
            ProvidesConsoleNetwork::class,
            ProvidesConsoleSecurity::class,
            ProvidesScheduledAuthRefresh::class,
        ],
    ],
];
```

目录名、`slug` 和后台安装时传入的 slug 必须一致。`key` 是 provider key，供应商 `interface_type`、商品 `provision_module` 或服务 `provision_data.provider` 使用该值时，平台才会解析到该插件。

## 运行时动作

servers 插件入口通过 `execute(array $request)` 暴露控制面动作：

| action | 说明 |
| --- | --- |
| `server.metadata` | 返回 provider key、展示名和 capability 列表 |
| `server.supports` | 判断是否支持指定 capability |
| `server.resolve_capability` | 返回实际 capability 对象 |
| `server.health_check` | 后台检测插件加载和配置状态 |

数据面不建议暴露原始 HTTP action。商品、开通、续费、状态同步和控制台动作应通过 capability 对象上的显式方法调用。

## 能力边界

插件负责：

- 上游 API 地址、账号、密钥使用和协议封装。
- 登录、Token/JWT 缓存、刷新和过期重试。
- 商品、价格、库存、配置项和配置价格归一化。
- 开通、续费、状态同步、VNC、电源、重装、重置密码、NAT、安全组、流量包、升降级等上游动作。
- 返回稳定、受控、脱敏后的结构。

平台负责：

- 管理端 API、FormRequest、Resource 和统一响应。
- 供应商配置、商品绑定和本地商品生命周期。
- 订单、账单、余额、支付和服务实例状态机。
- 开通幂等、续费幂等、失败重试、队列调度和审计日志。
- 用户身份、服务归属、控制台限流、权限和操作日志。

插件禁止：

- 直接写本地订单、账单、支付、余额或服务实例核心状态。
- 自行注册 Laravel 路由、菜单、权限或迁移。
- 暴露 `server.request`、`server.raw_http` 这类原始 HTTP 动作。
- 在日志中输出密钥、JWT、密码、VNC token。

## 开发真实插件

1. 复制 `backend/plugins/servers/demo_servers/`。
2. 修改目录名、namespace、入口类、`slug`、`key` 和展示名。
3. 按真实能力保留或删减 capability 声明。
4. 把 `DemoServers` 中的模拟返回替换为插件内部 service 或 SDK 调用。
5. 保持平台状态机边界不变：插件只返回上游结果，平台负责本地业务推进。
6. 为 provider key 解析、capability 解析、商品、开通、续费、状态和控制台动作补测试。

## 验证

```bash
cd backend
php artisan test tests/Feature/PluginSimulationTest.php --filter=demo_servers
```

如改动真实上游解析或魔方财务链路，还需要追加：

```bash
cd backend
php artisan test tests/Feature/PluginRuntimeRegistryIntegrationTest.php tests/Feature/SupplierInterfaceTypeAliasRegressionTest.php tests/Unit/ProviderResolverTest.php
```
