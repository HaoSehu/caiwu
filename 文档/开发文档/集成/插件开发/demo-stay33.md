# Demo：Stay33 实名认证插件

当前实现路径：

```text
backend/plugins/certification/stay33/
├── Stay33Plugin.php
├── config.php
└── logic/
    └── Stay33.php
```

## 适用场景

实名认证插件负责对接第三方认证能力，例如初始化认证、生成扫码链接、查询认证状态。用户实名状态、认证记录、收费账单和认证通过后的业务解锁仍由平台控制。

## config.php demo

```php
<?php

declare(strict_types=1);

use Caiwu\Plugins\Certification\Stay33\Stay33Plugin;

return [
    'info' => [
        'domain' => 'verification',
        'slug' => 'stay33',
        'key' => 'stay33',
        'name' => 'Stay33 实名认证',
        'version' => '1.0.0',
        'entry' => Stay33Plugin::class,
        'capabilities' => ['personal', 'scan_url', 'query_status'],
        'extra' => [
            'legacy_settings' => [
                'group' => 'verification',
                'map' => [
                    'api' => 'verification_api',
                    'key' => 'verification_key',
                    'biz_code' => 'verification_biz_code',
                ],
            ],
            'selection_setting' => [
                'group' => 'verification',
                'key' => 'verification_driver',
                'value' => 'stay33',
            ],
        ],
    ],
    'config' => [
        'api' => ['title' => 'API 标识', 'type' => 'text', 'value' => '', 'required' => true],
        'key' => ['title' => '接口密钥', 'type' => 'text', 'value' => '', 'required' => true, 'secret' => true],
        'biz_code' => ['title' => '认证业务码', 'type' => 'text', 'value' => '', 'required' => true],
        'charge_enabled' => ['title' => '插件收费', 'type' => 'switch', 'value' => false],
        'amount' => ['title' => '收费金额', 'type' => 'text', 'value' => '0.00'],
        'free_times' => ['title' => '免费次数', 'type' => 'number', 'value' => 0],
    ],
];
```

## 入口类 demo

```php
<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Certification\Stay33;

use Caiwu\Plugins\Certification\Stay33\Logic\Stay33;

class Stay33Plugin extends Stay33 {}
```

## 业务类 demo

```php
<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Certification\Stay33\Logic;

use App\Services\Verification\Contracts\VerificationDriver;
use App\Services\Verification\Drivers\Stay33Driver;

class Stay33 implements VerificationDriver
{
    public function __construct(
        private readonly Stay33Driver $driver,
    ) {}

    public function key(): string
    {
        return $this->driver->key();
    }

    public function label(): string
    {
        return $this->driver->label();
    }

    // initialize/generateScanUrl/queryStatus 均委托给平台 driver。
}
```

## 实名认证边界

插件负责：

- 请求第三方初始化认证。
- 返回认证标识、二维码或扫码链接。
- 查询第三方认证状态。
- 归一化第三方返回结果。

平台负责：

- 用户实名状态变更。
- 身份证、企业信息去重。
- 认证记录和失败原因保存。
- 插件收费账单和支付后继续认证。
- 认证通过后的业务解锁、通知和审计。

## 测试要点

- 未填写 `api/key/biz_code` 不能启用。
- `key` 字段不明文回显。
- 启用后 `verification.verification_driver=stay33`。
- 查询认证状态只更新平台允许更新的实名字段。

推荐命令：

```bash
cd backend
php artisan test tests/Feature/AdminIntegrationPluginControllerTest.php tests/Feature/PluginRuntimeRegistryIntegrationTest.php
```
