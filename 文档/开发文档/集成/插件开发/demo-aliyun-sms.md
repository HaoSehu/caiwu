# Demo：阿里云短信插件

当前实现路径：

```text
backend/plugins/sms/aliyun/
├── AliyunPlugin.php
├── config.php
└── lib/
    └── AliyunSmsService.php
```

## 适用场景

短信插件用于发送验证码短信。验证码生成、频控、日志脱敏、用户通知偏好仍由平台服务控制。

## config.php demo

```php
<?php

declare(strict_types=1);

use Caiwu\Plugins\Sms\Aliyun\AliyunPlugin;

return [
    'info' => [
        'domain' => 'sms',
        'slug' => 'aliyun',
        'key' => 'aliyun',
        'name' => '阿里云短信',
        'version' => '1.0.0',
        'entry' => AliyunPlugin::class,
        'capabilities' => ['verify_code'],
        'extra' => [
            'legacy_settings' => [
                'group' => 'notification',
                'map' => [
                    'access_key' => 'sms_access_key',
                    'secret_key' => 'sms_secret_key',
                    'sign_name' => 'sms_sign_name',
                    'template_code' => 'sms_template_code',
                ],
            ],
            'selection_setting' => [
                'group' => 'notification',
                'key' => 'sms_driver',
                'value' => 'aliyun',
            ],
        ],
    ],
    'config' => [
        'access_key' => ['title' => 'Access Key', 'type' => 'text', 'value' => '', 'required' => true, 'secret' => true],
        'secret_key' => ['title' => 'Secret Key', 'type' => 'text', 'value' => '', 'required' => true, 'secret' => true],
        'sign_name' => ['title' => '短信签名', 'type' => 'text', 'value' => '', 'required' => true],
        'template_code' => ['title' => '短信模板', 'type' => 'text', 'value' => '', 'required' => true],
    ],
];
```

## 入口类 demo

```php
<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Sms\Aliyun;

use Caiwu\Plugins\Sms\Aliyun\Lib\AliyunSmsService;

class AliyunPlugin extends AliyunSmsService {}
```

## 业务类 demo

```php
<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Sms\Aliyun\Lib;

use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\Drivers\AliyunSmsDriver;

class AliyunSmsService implements SmsDriver
{
    public function __construct(
        private readonly AliyunSmsDriver $driver,
    ) {}

    public function key(): string
    {
        return $this->driver->key();
    }

    public function label(): string
    {
        return $this->driver->label();
    }

    // sendVerifyCode 委托平台 AliyunSmsDriver。
}
```

## 短信边界

插件负责：

- 对接短信供应商。
- 返回发送结果和请求 ID。
- 归一化供应商错误。

平台负责：

- 验证码生成。
- IP、手机号、目标频控。
- 模板变量。
- 短信日志脱敏。
- 用户通知偏好。

## 测试要点

- 未配置 Access Key、Secret Key、签名、模板号不能启用。
- `access_key`、`secret_key` 不明文回显。
- 启用后 `notification.sms_driver=aliyun`。
- 发送失败不能泄漏验证码和密钥。

推荐命令：

```bash
cd backend
php artisan test tests/Feature/PluginRuntimeRegistryIntegrationTest.php
```
