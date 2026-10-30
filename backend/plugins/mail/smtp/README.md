# Single SMTP 插件

该插件把 Single SMTP 接入 Caiwu 的 `mail` 能力域，slug 为 `smtp`、plugin_key 为 `smtp`。

## 目录结构

```text
backend/plugins/mail/smtp/
├── SmtpPlugin.php
├── lib/SmtpMailService.php
└── config.php
```

## 配置项

在后台插件管理中按下列字段填写；标记为「加密」的字段保存后不会明文回显。

| 字段 | 类型 | 必填 | 加密 | 说明 |
| --- | --- | --- | --- | --- |
| `host` | text | 否 | 否 |  |
| `port` | number | 否 | 否 |  |
| `username` | text | 否 | 否 |  |
| `password` | password | 否 | 是 |  |
| `from_name` | text | 否 | 否 |  |
| `encryption` | select | 否 | 否 |  |
| `timeout_seconds` | number | 否 | 否 |  |
| `rate_limit_enabled` | switch | 否 | 否 | 限制使用此插件发送邮箱验证码的单 IP 频率。 |
| `ip_minute_limit` | number | 否 | 否 | 设为 0 表示不限制。 |

## 能力边界

插件只负责第三方协议适配与协议级错误翻译；订单、账单、服务状态、审计日志、限流与幂等仍由平台服务层负责。
