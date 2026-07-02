# Demo：多 SMTP 轮询邮件插件

当前实现路径：

```text
backend/plugins/mail/multi_smtp_round_robin/
├── MultiSmtpRoundRobinPlugin.php
├── config.php
└── lib/
    └── MultiSmtpRoundRobinService.php
```

## 适用场景

该插件让邮件发送支持多个 SMTP 账号轮询。默认单 SMTP 可以继续作为平台内置能力；需要多账号容错、冷却、轮询时启用本插件。

## config.php demo

```php
<?php

declare(strict_types=1);

use Caiwu\Plugins\Mail\MultiSmtpRoundRobin\MultiSmtpRoundRobinPlugin;

return [
    'info' => [
        'domain' => 'mail',
        'slug' => 'multi_smtp_round_robin',
        'key' => 'multi_smtp_round_robin',
        'name' => '多 SMTP 轮询',
        'version' => '1.0.0',
        'entry' => MultiSmtpRoundRobinPlugin::class,
        'capabilities' => ['smtp', 'round_robin', 'cooldown'],
        'extra' => [
            'selection_setting' => [
                'group' => 'notification',
                'key' => 'mail_driver',
                'value' => 'multi_smtp_round_robin',
            ],
        ],
    ],
    'config' => [
        'accounts' => ['title' => 'SMTP 账号列表', 'type' => 'json', 'value' => [], 'required' => true, 'secret' => true],
        'cooldown_seconds' => ['title' => '失败冷却秒数', 'type' => 'number', 'value' => 60],
    ],
];
```

## accounts demo

后台现在使用账号列表维护 `accounts`，后端真实保存结构如下：

```json
[
  {
    "host": "smtp.example.com",
    "port": 465,
    "username": "mail@example.com",
    "password": "secret",
    "from_name": "Caiwu",
    "encryption": "ssl",
    "enabled": true
  }
]
```

安全规则：

- `accounts` 是 `secret=true` 字段，加密保存。
- 后台详情只返回脱敏预览。
- 编辑账号时密码留空，后端按账号索引保留旧密码。
- `enabled=false` 的账号会在发送时跳过。

## 入口类 demo

```php
<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Mail\MultiSmtpRoundRobin;

use Caiwu\Plugins\Mail\MultiSmtpRoundRobin\Lib\MultiSmtpRoundRobinService;

class MultiSmtpRoundRobinPlugin extends MultiSmtpRoundRobinService {}
```

## 业务类 demo

```php
<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Mail\MultiSmtpRoundRobin\Lib;

use App\Services\Mail\Contracts\MailDriver;

class MultiSmtpRoundRobinService implements MailDriver
{
    public function key(): string
    {
        return 'multi_smtp_round_robin';
    }

    public function label(): string
    {
        return '多 SMTP 轮询';
    }

    public function sendHtml(string $to, string $subject, string $html, array $context = []): void
    {
        // 读取插件配置 accounts。
        // 跳过 enabled=false 的账号。
        // 从 cursor 位置开始轮询。
        // 发送失败后给该账号设置 cooldown。
        // 全部失败后抛出 BusinessException。
    }
}
```

## 邮件边界

插件负责：

- 选择一个可用 SMTP 账号。
- 调用 `SmtpMailTransport` 发送 HTML 邮件。
- 失败账号冷却。
- 保存轮询游标。

平台 `NotificationService` 负责：

- 模板选择。
- 模板变量渲染。
- 邮件正文包装。
- 验证码内容脱敏。
- 邮件日志和通知日志。

## 测试要点

- 第一个账号失败后能切到第二个账号。
- 暂停账号不会参与发送。
- 编辑账号时密码留空不会丢失原密码。
- 后台只显示脱敏账号预览。

推荐命令：

```bash
cd backend
php artisan test tests/Feature/MultiSmtpRoundRobinPluginTest.php
```
